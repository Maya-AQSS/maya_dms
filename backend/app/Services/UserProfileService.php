<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\JwtUser;
use App\Repositories\Contracts\UserPermissionRepositoryInterface;
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
        private readonly UserPermissionRepositoryInterface $userPermissionRepository,
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

            // MOCK locale — la columna `locale` aún no existe en `v_app_users` (FDW Odoo).
            // Cuando se añada en maya_core_employee + se exponga en la vista, leer con:
            //   $fdwUser['locale'] ?? 'es'.
            $profile = [
                'id'             => $fdwUser['id'],
                'email'          => $fdwUser['email'] ?? null,
                'name'           => $fdwUser['name'] ?? null,
                'department'     => $fdwUser['department'] ?? $jwtProfile['department'] ?? $jwtProfile['departamento'] ?? null,
                'locale'         => $fdwUser['locale'] ?? 'es',
                'study_type_ids' => $this->repository->findStudyTypeIdsByUserId($userId),
                'study_ids'      => $this->repository->findStudyIdsByUserId($userId),
                'module_ids'     => $this->repository->findModuleIdsByUserId($userId),
                'team_ids'       => array_column($teams, 'id'),
                'permissions'    => $this->userPermissionRepository->findPermissionCodesByUserId($userId),
                'teams'          => $teams,
                'source'         => 'fdw',
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
        $this->userPermissionRepository->forgetCachedCodesForUser($userId);
        Cache::forget(self::CACHE_PREFIX . $userId);
    }

    /**
     * Construye perfil parcial desde los datos del JWT cuando FDW no está disponible.
     */
    private function buildFallbackProfile(string $userId, array $jwtProfile): array
    {
        $scopes = $this->scopeListsFromJwtProfile($jwtProfile);

        return [
            'id'             => $jwtProfile['id'] ?? $userId,
            'email'          => $jwtProfile['email'] ?? null,
            'name'           => $jwtProfile['name'] ?? null,
            'department'     => $jwtProfile['department'] ?? $jwtProfile['departamento'] ?? null,
            'locale'         => 'es', // MOCK — ver getProfile().
            'study_type_ids' => $scopes['study_type_ids'],
            'study_ids'      => $scopes['study_ids'],
            'module_ids'     => $scopes['module_ids'],
            'team_ids'       => [],
            'permissions'    => $this->userPermissionRepository->findPermissionCodesByUserId($userId),
            'teams'          => [],
            'source'         => 'jwt_fallback',
        ];
    }

    /**
     * Listas de ámbito académico extraídas del JWT para el perfil de fallback.
     * Solo jerarquía: los equipos no viajan en el token.
     *
     * @return array{study_type_ids: list<string>, study_ids: list<string>, module_ids: list<string>}
     */
    private function scopeListsFromJwtProfile(array $jwtProfile): array
    {
        return [
            'study_type_ids' => JwtUser::mergeScopeIds($jwtProfile['study_type_ids'] ?? null, $jwtProfile['study_type_id'] ?? null),
            'study_ids'      => JwtUser::mergeScopeIds($jwtProfile['study_ids'] ?? null, $jwtProfile['study_id'] ?? null),
            'module_ids'     => array_values(array_unique(array_merge(
                JwtUser::mergeScopeIds($jwtProfile['module_ids'] ?? null, $jwtProfile['module_id'] ?? null),
                JwtUser::mergeScopeIds($jwtProfile['course_module_ids'] ?? null, $jwtProfile['course_module_id'] ?? null),
            ))),
        ];
    }

}
