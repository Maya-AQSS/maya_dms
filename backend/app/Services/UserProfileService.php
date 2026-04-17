<?php

namespace App\Services;

use App\Repositories\Contracts\UserProfileRepositoryInterface;
use App\Services\Contracts\UserProfileServiceInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Servicio que orquesta la obtención del perfil completo del usuario activo.
 */
class UserProfileService implements UserProfileServiceInterface
{
    private const CACHE_PREFIX = 'user_profile:';
    private const CACHE_TTL_SECONDS = 900; // 15 minutos

    public function __construct(
        private readonly UserProfileRepositoryInterface $repository,
    ) {}

    /**
     * Devuelve el perfil completo del usuario activo.
     *
     * Flujo:
     * 1. Busca en caché Redis (clave user_profile:{user_id}, TTL 15 min).
     * 2. Si no hay caché, consulta FDW con timeout de 500 ms.
     * 3. Si FDW falla o no responde, usa los datos mínimos del JWT como fallback.
     *
     * @param string $userId     ID del usuario (claim `sub` del JWT).
     * @param array  $jwtProfile Datos mínimos extraídos del JWT para fallback.
     */
    public function getProfile(string $userId, array $jwtProfile): array
    {
        $cacheKey = self::CACHE_PREFIX . $userId;

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $fdwUser = $this->repository->findById($userId);

            if ($fdwUser === null) {
                return $this->buildFallbackProfile($userId, $jwtProfile);
            }

            $teams = $this->repository->findTeamsByUserId($userId);

            $profile = [
                'id'              => $fdwUser['id'],
                'email'           => $fdwUser['email'] ?? null,
                'name'            => $fdwUser['name'] ?? null,
                'department'      => $fdwUser['department'] ?? null,
                'organization_id' => $jwtProfile['organization_id'] ?? null,
                'roles'           => $jwtProfile['roles'] ?? [],
                'teams'           => $teams,
                'source'          => 'fdw',
            ];

            Cache::put($cacheKey, $profile, self::CACHE_TTL_SECONDS);

            return $profile;
        } catch (\Throwable $e) {
            Log::warning('FDW query failed, using JWT fallback', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);

            return $this->buildFallbackProfile($userId, $jwtProfile);
        }
    }

    /**
     * Invalida el caché del perfil de un usuario específico.
     */
    public function invalidateCache(string $userId): void
    {
        Cache::forget(self::CACHE_PREFIX . $userId);
    }

    /**
     * Construye perfil parcial desde los datos del JWT cuando FDW no está disponible.
     */
    private function buildFallbackProfile(string $userId, array $jwtProfile): array
    {
        return [
            'id'              => $jwtProfile['id'] ?? $userId,
            'email'           => $jwtProfile['email'] ?? null,
            'name'            => $jwtProfile['name'] ?? null,
            'department'      => $jwtProfile['department'] ?? $jwtProfile['departamento'] ?? null,
            'organization_id' => $jwtProfile['organization_id'] ?? null,
            'roles'           => $jwtProfile['roles'] ?? [],
            'teams'           => [],
            'source'          => 'jwt_fallback',
        ];
    }
}
