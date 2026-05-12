<?php

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
 * guard, y adapta el array resultante al `UserProfileDto` que espera el
 * paquete compartido.
 */
final class FdwUserProfileResolver implements UserProfileResolverInterface
{
    private const COMMON_KEYS = ['id', 'email', 'name', 'locale'];

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

        return new UserProfileDto(
            id:     (string) ($profile['id'] ?? $userId),
            email:  $this->stringOrNull($profile['email'] ?? null),
            name:   $this->stringOrNull($profile['name'] ?? null),
            locale: $locale,
            extra:  array_diff_key($profile, array_flip(self::COMMON_KEYS)),
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
}
