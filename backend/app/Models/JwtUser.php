<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\Access\Authorizable;

/**
 * DTO que representa al usuario autenticado a partir de los claims JWT
 * más datos resueltos en el guard (p. ej. permisos desde BD).
 *
 * No es un modelo Eloquent — no hay tabla 'jwt_users'.
 * Se construye en JwtMiddleware y se inyecta en el auth guard.
 *
 * Los roles de realm (Keycloak) no se materializan aquí: la autorización de
 * negocio usa {@see self::hasPermission()} y claims de ámbito académico cuando apliquen.
 */
class JwtUser implements Authenticatable, AuthorizableContract
{
    use Authorizable;

    public readonly string $id;

    public readonly ?string $email;

    public readonly ?string $name;

    public readonly ?string $department;

    /**
     * Códigos de permiso desde BD (`user_permissions`), resueltos en el guard.
     *
     * @var list<string>
     */
    public readonly array $permissions;

    public readonly string $scope;

    /**
     * IDs de contexto académico.
     *
     * @var list<string>
     */
    public readonly array $studyTypeIds;

    /**
     * IDs de estudios.
     *
     * @var list<string>
     */
    public readonly array $studyIds;

    /**
     * IDs de módulos.
     *
     * @var list<string>
     */
    public readonly array $moduleIds;

    /**
     * IDs de equipos.
     *
     * @var list<string>
     */
    public readonly array $teamIds;

    public function __construct(array $claims)
    {
        $this->id = $claims['id'];
        $this->email = $claims['email'] ?? null;
        $this->name = $claims['name'] ?? null;
        $this->department = $claims['department'] ?? $claims['departamento'] ?? null;
        $this->permissions = array_values(array_unique(array_map(
            static fn ($c): string => (string) $c,
            $claims['permissions'] ?? [],
        )));
        $this->scope = $claims['scope'] ?? '';

        $this->studyTypeIds = self::mergeScopeIds(
            $claims['study_type_ids'] ?? null,
            $claims['study_type_id'] ?? null,
        );
        $this->studyIds = self::mergeScopeIds(
            $claims['study_ids'] ?? null,
            $claims['study_id'] ?? null,
        );
        $this->moduleIds = array_values(array_unique(array_merge(
            self::mergeScopeIds($claims['module_ids'] ?? null, $claims['module_id'] ?? null),
            self::mergeScopeIds($claims['course_module_ids'] ?? null, $claims['course_module_id'] ?? null),
        )));
        $this->teamIds = self::mergeScopeIds(
            $claims['team_ids'] ?? null,
            $claims['team_id'] ?? null,
        );
    }

    /**
     * Comprueba si el usuario tiene un permiso concedido en BD.
     */
    public function hasPermission(string $code): bool
    {
        return in_array($code, $this->permissions, strict: true);
    }

    /**
     * Normaliza lista + valor escalar de claims (arrays o JSON en string).
     *
     * @return list<string>
     */
    public static function mergeScopeIds(mixed $listClaim, mixed $scalarClaim): array
    {
        $out = [];

        if (is_string($listClaim) && $listClaim !== '') {
            $decoded = json_decode($listClaim, true);
            $listClaim = is_array($decoded) ? $decoded : null;
        }

        if (is_array($listClaim)) {
            foreach ($listClaim as $v) {
                if ($v !== null && $v !== '') {
                    $out[] = (string) $v;
                }
            }
        }

        if (is_string($scalarClaim) && $scalarClaim !== '') {
            $out[] = $scalarClaim;
        }

        return array_values(array_unique($out));
    }

    // ── Authenticatable contract ──────────────────────────────

    /**
     * Obtiene el nombre del identificador de autenticación.
     */
    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    /**
     * Obtiene el identificador de autenticación.
     */
    public function getAuthIdentifier(): string
    {
        return $this->id;
    }

    /**
     * Obtiene el nombre del identificador de contraseña.
     */
    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    /**
     * Obtiene la contraseña del usuario.
     */
    public function getAuthPassword(): string
    {
        return '';
    }

    /**
     * Obtiene el token de recuerdo del usuario.
     */
    public function getRememberToken(): string
    {
        return '';
    }

    /**
     * Establece el token de recuerdo del usuario.
     */
    public function setRememberToken($value): void {}

    /**
     * Obtiene el nombre del token de recuerdo del usuario.
     */
    public function getRememberTokenName(): string
    {
        return '';
    }
}
