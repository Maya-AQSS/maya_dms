<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Users\JwtProfileDto;
use App\DTOs\Users\UserProfileDto;

interface UserProfileServiceInterface
{
    /**
     * Devuelve el perfil del usuario autenticado.
     *
     * @param  string  $userId  Claim sub del JWT.
     * @param  JwtProfileDto  $jwtProfile  Datos mínimos del JWT para fallback.
     */
    public function getProfile(string $userId, JwtProfileDto $jwtProfile): UserProfileDto;

    /**
     * Invalida el perfil cacheado del usuario.
     */
    public function invalidateCache(string $userId): void;
}
