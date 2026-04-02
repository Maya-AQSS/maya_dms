<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Lcobucci\JWT\Signer\Key\InMemory;
use RuntimeException;

class JwksService
{
    private const CACHE_KEY = 'jwks_keys';
    private const CACHE_TTL = 3600; // 1 hora

    /**
     * Devuelve la clave pública RSA correspondiente al kid del token.
     * Primero busca en caché Redis; si no existe o ha expirado, descarga del JWKS.
     * Si el endpoint JWKS no responde, usa las claves cacheadas como fallback (Zero Trust).
     */
    public function getPublicKey(string $kid): InMemory
    {
        $keys = $this->getCachedKeys() ?? $this->fetchAndCacheKeys();

        if (! isset($keys[$kid])) {
            // Kid no encontrado en caché — refrescar una vez
            $keys = $this->fetchAndCacheKeys(force: true);
        }

        if (! isset($keys[$kid])) {
            throw new RuntimeException("JWKS key not found for kid: {$kid}");
        }

        return InMemory::plainText($keys[$kid]);
    }

    private function getCachedKeys(): ?array
    {
        return Cache::get(self::CACHE_KEY);
    }

    private function fetchAndCacheKeys(bool $force = false): array
    {
        if (! $force) {
            $cached = $this->getCachedKeys();
            if ($cached !== null) {
                return $cached;
            }
        }

        $jwksUrl = config('auth.jwks_url');

        try {
            $response = Http::timeout(5)->get($jwksUrl);

            if (! $response->successful()) {
                throw new RuntimeException("JWKS endpoint returned {$response->status()}");
            }

            $keys = $this->parseJwks($response->json());
            Cache::put(self::CACHE_KEY, $keys, self::CACHE_TTL);

            return $keys;
        } catch (\Exception $e) {
            Log::warning('JWKS fetch failed, using cached keys (Zero Trust fallback)', [
                'error' => $e->getMessage(),
                'url'   => $jwksUrl,
            ]);

            $cached = $this->getCachedKeys();

            if ($cached === null) {
                throw new RuntimeException('JWKS unavailable and no cached keys found: ' . $e->getMessage());
            }

            return $cached;
        }
    }

    /**
     * Convierte el array JWKS en un mapa kid → PEM.
     */
    private function parseJwks(array $jwks): array
    {
        $keys = [];

        foreach ($jwks['keys'] ?? [] as $key) {
            if (($key['kty'] ?? '') !== 'RSA' || ($key['use'] ?? '') !== 'sig') {
                continue;
            }

            $kid = $key['kid'];
            $pem = $this->rsaJwkToPem($key);

            if ($pem !== null) {
                $keys[$kid] = $pem;
            }
        }

        return $keys;
    }

    private function rsaJwkToPem(array $jwk): ?string
    {
        if (empty($jwk['n']) || empty($jwk['e'])) {
            return null;
        }

        $modulus  = $this->base64UrlDecode($jwk['n']);
        $exponent = $this->base64UrlDecode($jwk['e']);

        // Construir el DER de la clave pública RSA y convertirlo a PEM
        $modulus  = ltrim($modulus, "\x00");
        // Si el bit más significativo es 1, añadir byte 0x00 para evitar interpretación negativa
        if (ord($modulus[0]) > 0x7f) {
            $modulus = "\x00" . $modulus;
        }

        $rsaPublicKey = $this->derEncode([
            'sequence' => [
                'sequence' => [
                    // OID for rsaEncryption
                    "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01",
                    "\x05\x00",
                ],
                'bitString' => $this->derEncode([
                    'sequence' => [
                        'integer' => $modulus,
                        'integer2' => $exponent,
                    ],
                ]),
            ],
        ]);

        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($rsaPublicKey), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }

    private function derEncode(array $data): string
    {
        // Serialización DER simplificada para claves públicas RSA (PKCS#8)
        $result = '';

        foreach ($data as $type => $value) {
            if ($type === 'sequence') {
                $content = $this->derEncode($value);
                $result .= "\x30" . $this->derLength(strlen($content)) . $content;
            } elseif (str_starts_with($type, 'integer')) {
                $result .= "\x02" . $this->derLength(strlen($value)) . $value;
            } elseif ($type === 'bitString') {
                $inner = "\x00" . $value;
                $result .= "\x03" . $this->derLength(strlen($inner)) . $inner;
            } else {
                $result .= $value;
            }
        }

        return $result;
    }

    private function derLength(int $len): string
    {
        if ($len < 0x80) {
            return chr($len);
        }
        if ($len < 0x100) {
            return "\x81" . chr($len);
        }

        return "\x82" . chr($len >> 8) . chr($len & 0xff);
    }

    private function base64UrlDecode(string $data): string
    {
        $padded = str_pad(strtr($data, '-_', '+/'), strlen($data) + (4 - strlen($data) % 4) % 4, '=');

        return base64_decode($padded);
    }
}
