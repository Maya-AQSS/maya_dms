<?php

declare(strict_types=1);

namespace App\Repositories\Resolvers;

use App\DTOs\Users\JwtProfileDto;
use App\Services\Contracts\UserProfileServiceInterface as LocalUserProfileServiceInterface;
use Maya\Profile\Dtos\UserProfileDto;
use Maya\Profile\Enums\Locale;
use Maya\Profile\Repositories\Contracts\UserProfileResolverInterface;

/**
 * Resolver de perfil específico de maya_dms.
 *
 * Delega al `App\Services\UserProfileService` existente (FDW + Redis cache +
 * fallback JWT) que también consume el auth guard, y adapta el array
 * resultante al `UserProfileDto` canónico que espera el paquete compartido.
 *
 * Forma canónica del DTO devuelto (cross-app, snake_case en inglés):
 *   `permissions`, `study_type_ids`, `study_ids`, `module_ids`, `team_ids`.
 *
 * Los objetos completos de equipos (con nombre, descripción, etc.) NO se
 * exponen aquí — el frontend los consulta vía `GET /me/academic-context`
 * (paquete maya-shared-profile-laravel).
 *
 * Los nombres internos del Service coinciden con la forma canónica, por lo
 * que el resolver SOLO filtra los campos sobrantes del payload — no renombra.
 *
 * Campos eliminados del payload `/me` (no se exponen):
 * - `department`/`departamento`: claim del JWT, no debe ir en /me.
 * - `roles`: la autorización pasa por `permissions`.
 * - `organization_id`/`organizacion_id`: obsoleto.
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
        $profile = $this->localProfile->getProfile($userId, JwtProfileDto::fromArray($jwtProfile));

        $localeValue = is_string($profile['locale'] ?? null) ? $profile['locale'] : null;
        $locale = ($localeValue !== null && Locale::tryFrom($localeValue) !== null)
            ? Locale::from($localeValue)
            : Locale::default();

        $extra = array_diff_key($profile, array_flip(self::COMMON_KEYS));
        $extra = array_diff_key($extra, array_flip(self::EXTRA_DROP_KEYS));

        // Garantizar la forma canónica: arrays presentes con type list.
        $extra['permissions'] = $this->arrayList($extra['permissions'] ?? []);
        $extra['study_type_ids'] = $this->arrayList($extra['study_type_ids'] ?? []);
        $extra['study_ids'] = $this->arrayList($extra['study_ids'] ?? []);
        $extra['module_ids'] = $this->arrayList($extra['module_ids'] ?? []);
        $extra['team_ids'] = $this->arrayList($extra['team_ids'] ?? []);
        unset($extra['teams']);

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
