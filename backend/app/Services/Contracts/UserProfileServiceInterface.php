<?php

namespace App\Services\Contracts;

interface UserProfileServiceInterface
{
    /**
     * Devuelve el perfil del usuario autenticado.
     *
     * @param string $userId     Claim sub del JWT.
     * @param array  $jwtProfile Datos mínimos del JWT para fallback.
     */
    public function getProfile(string $userId, array $jwtProfile): array;

    /**
     * Invalida el perfil cacheado del usuario.
     */
    public function invalidateCache(string $userId): void;
}