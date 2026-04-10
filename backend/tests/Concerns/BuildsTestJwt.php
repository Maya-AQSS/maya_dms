<?php

namespace Tests\Concerns;

use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;

trait BuildsTestJwt
{
    /**
     * @return array{0: string, 1: string} [privatePem, publicPem]
     */
    protected function generateRsaKeyPairForTests(): array
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($resource, $privatePem);
        $details = openssl_pkey_get_details($resource);

        return [$privatePem, $details['key']];
    }

    /**
     * @param  list<string>  $realmRoles  Roles en realm_access.roles (Keycloak).
     */
    protected function buildJwtForSub(
        string $privatePem,
        string $publicPem,
        string $kid,
        string $sub,
        string $issuer = 'test-issuer',
        string $audience = 'test-audience',
        array $realmRoles = [],
    ): string {
        $config = Configuration::forAsymmetricSigner(
            new Sha256,
            InMemory::plainText($privatePem),
            InMemory::plainText($publicPem),
        );

        $now = new DateTimeImmutable;

        $builder = $config->builder()
            ->issuedBy($issuer)
            ->permittedFor($audience)
            ->issuedAt($now->modify('-2 hours'))
            ->canOnlyBeUsedAfter($now->modify('-2 hours'))
            ->expiresAt($now->modify('+1 hour'))
            ->relatedTo($sub)
            ->withClaim('email', 'test@example.com');

        if ($realmRoles !== []) {
            $builder = $builder->withClaim('realm_access', ['roles' => $realmRoles]);
        }

        return $builder
            ->withHeader('kid', $kid)
            ->getToken(new Sha256, InMemory::plainText($privatePem))
            ->toString();
    }
}
