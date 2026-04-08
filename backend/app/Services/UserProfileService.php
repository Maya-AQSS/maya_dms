<?php

namespace App\Services;

use App\Repositories\UserProfileRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Servicio que orquesta la obtención del perfil completo del usuario activo.
 *
 * Escenarios cubiertos:
 *   - Escenario 1: Delega a UserProfileRepository que siempre filtra por user_id.
 *   - Escenario 2: Caché Redis con clave user_profile:{user_id} y TTL 15 min (900 s).
 *   - Escenario 3: Fallback a datos mínimos del JWT cuando FDW falla o no responde
 *                   dentro del timeout; la operación continúa con información parcial.
 *   - Requisito de rendimiento: con caché activo responde en < 5 ms (lectura Redis).
 */
class UserProfileService
{
    private const CACHE_PREFIX = 'user_profile:';
    private const CACHE_TTL_SECONDS = 900; // 15 minutos

    public function __construct(
        private readonly UserProfileRepository $repository,
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
                return $this->buildFallbackProfile($jwtProfile);
            }

            $groups = $this->repository->findGroupsByUserId($userId);

            $profile = [
                'id'              => $fdwUser['id'],
                'email'           => $fdwUser['email'],
                'name'            => $fdwUser['name'],
                'first_name'      => $fdwUser['first_name'],
                'last_name'       => $fdwUser['last_name'],
                'username'        => $fdwUser['username'],
                'is_active'       => $fdwUser['is_active'],
                'organization_id' => $jwtProfile['organization_id'] ?? null,
                'roles'           => $jwtProfile['roles'] ?? [],
                'groups'          => $groups,
                'source'          => 'fdw',
            ];

            Cache::put($cacheKey, $profile, self::CACHE_TTL_SECONDS);

            return $profile;
        } catch (\Throwable $e) {
            Log::warning('FDW query failed, using JWT fallback', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);

            return $this->buildFallbackProfile($jwtProfile);
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
    private function buildFallbackProfile(array $jwtProfile): array
    {
        return [
            'id'              => $jwtProfile['id'],
            'email'           => $jwtProfile['email'] ?? null,
            'name'            => $jwtProfile['name'] ?? null,
            'first_name'      => null,
            'last_name'       => null,
            'username'        => null,
            'is_active'       => null,
            'organization_id' => $jwtProfile['organization_id'] ?? null,
            'roles'           => $jwtProfile['roles'] ?? [],
            'groups'          => [],
            'source'          => 'jwt_fallback',
        ];
    }
}
