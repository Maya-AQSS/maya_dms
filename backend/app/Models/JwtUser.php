<?php

namespace App\Models;

use App\Enums\TemplateVisibilityLevel;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\Access\Authorizable;

/**
 * DTO que representa al usuario autenticado a partir de los claims JWT.
 * No es un modelo Eloquent — no hay tabla 'jwt_users'.
 * Se construye en JwtMiddleware y se inyecta en el auth guard.
 *
 * Sin usuarios locales: Auth::user() siempre devuelve este objeto,
 * construido desde claims JWT + caché Redis.
 */
class JwtUser implements Authenticatable, AuthorizableContract
{
    use Authorizable;

    public readonly string $id;

    public readonly ?string $email;

    public readonly ?string $name;

    public readonly ?string $department;

    public readonly ?string $organizationId;

    public readonly array $roles;

    public readonly string $scope;

    public function __construct(array $claims)
    {
        $this->id = $claims['id'];
        $this->email = $claims['email'] ?? null;
        $this->name = $claims['name'] ?? null;
        $this->department = $claims['department'] ?? null;
        $this->organizationId = $claims['organization_id'] ?? null;
        $this->roles = $claims['roles'] ?? [];
        $this->scope = $claims['scope'] ?? '';
    }

    /**
     * Verifica si el usuario tiene un rol específico.
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, strict: true);
    }

    /**
     * Puede crear o fijar visibilidad de plantilla distinta de {@see TemplateVisibilityLevel::Personal}
     * (global, tipo de estudio, estudio, módulo, grupo).
     */
    public function canManageSharedTemplateVisibility(): bool
    {
        foreach (config('auth.template_shared_visibility_roles', []) as $role) {
            if ($this->hasRole((string) $role)) {
                return true;
            }
        }

        return false;
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
