<?php

declare(strict_types=1);

namespace App\Repositories\Resolvers;

use App\Services\Contracts\UserProfileServiceInterface as LocalUserProfileServiceInterface;
use Maya\Profile\Dtos\UserProfileDto;
use Maya\Profile\Enums\Locale;
use Maya\Profile\Repositories\Contracts\UserProfileResolverInterface;

/**
 * Resolver de perfil específico de maya_dms.
 *
 * Delega al `App\Services\UserProfileService` existente (FDW + Redis cache +
 * fallback JWT) que también consumen `AcademicHierarchyController` y el auth
 * guard, y adapta el array resultante al `UserProfileDto` canónico que espera
 * el paquete compartido.
 *
 * Forma canónica del DTO devuelto (cross-app, 2026-05-18 — campos en español):
 *   `permisos`, `tipo_estudios`, `estudios`, `modulos`, `equipos`.
 *
 * Los nombres internos del Service (`study_type_ids`, `study_ids`,
 * `module_ids`, `team_ids`, `permissions`, `teams`) se mantienen para no
 * romper consumidores HTTP internos (AcademicHierarchyController, auth
 * guard, etc.). El renombrado vive ÚNICAMENTE en este resolver, que es
 * quien proyecta al endpoint público `/me`.
 *
 * Campos eliminados del payload `/me` (no se exponen):
 * - `department`/`departamento`: claim del JWT, no debe ir en /me.
 * - `roles`: la autorización pasa por `permisos`.
 * - `organizacion_id`: obsoleto.
 * - `source`: detalle interno de implementación.
 */
final class FdwUserProfileResolver implements UserProfileResolverInterface
{
    private const COMMON_KEYS = ['id', 'email', 'name', 'locale'];

    private const EXTRA_DROP_KEYS = [
        'department',
        'departamento',
        'organization_id',
        'organizacion_id',
        'roles',
        'source',
    ];

    public function __construct(
        private readonly LocalUserProfileServiceInterface $localProfile,
    ) {}

    public function resolve(string $userId, array $jwtProfile): UserProfileDto
    {
        $profile = $this->localProfile->getProfile($userId, $jwtProfile);

        $localeValue = is_string($profile['locale'] ?? null) ? $profile['locale'] : null;
        $locale = ($localeValue !== null && Locale::tryFrom($localeValue) !== null)
            ? Locale::from($localeValue)
            : Locale::default();

        $extra = array_diff_key($profile, array_flip(self::COMMON_KEYS));
        $extra = array_diff_key($extra, array_flip(self::EXTRA_DROP_KEYS));

        // Renombrado: nombres canónicos en español para el payload público.
        $extra['permisos'] = $this->arrayList($extra['permissions'] ?? []);
        $extra['tipo_estudios'] = $this->arrayList($extra['study_type_ids'] ?? []);
        $extra['estudios'] = $this->arrayList($extra['study_ids'] ?? []);
        $extra['modulos'] = $this->arrayList($extra['module_ids'] ?? []);
        $extra['equipos'] = is_array($extra['teams'] ?? null) ? $extra['teams'] : [];

        // Limpiar las claves internas del Service tras el remap (no exponer).
        unset(
            $extra['permissions'],
            $extra['study_type_ids'],
            $extra['study_ids'],
            $extra['module_ids'],
            $extra['team_ids'],
            $extra['teams'],
        );

        return new UserProfileDto(
            id: (string) ($profile['id'] ?? $userId),
            email: $this->stringOrNull($profile['email'] ?? null),
            name: $this->stringOrNull($profile['name'] ?? null),
            locale: $locale,
            extra: $extra,
        );
    }

    public function invalidate(string $userId): void
    {
        $this->localProfile->invalidateCache($userId);
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value === '' ? null : $value;
        }

        return null;
    }

    /**
     * @return list<mixed>
     */
    private function arrayList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values($value);
    }
}
