<?php

namespace App\Services\Contracts;

use Lcobucci\JWT\Signer\Key\InMemory;

interface JwksServiceInterface
{
    /**
     * Clave pública RSA para el kid indicado (JWKS remoto + caché).
     */
    public function getPublicKey(string $kid): InMemory;
}
