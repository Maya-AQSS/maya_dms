<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * DTO que representa al usuario autenticado a partir de los claims JWT.
 * No es un modelo Eloquent — no hay tabla 'jwt_users'.
 * Se construye en JwtMiddleware y se inyecta en el auth guard.
 *
 * Sin usuarios locales: Auth::user() siempre devuelve este objeto,
 * construido desde claims JWT + caché Redis.
 */
class JwtUser implements Authenticatable
{
    public readonly string $id;
    public readonly ?string $email;
    public readonly ?string $name;
    public readonly ?string $organizationId;
    public readonly array $roles;

    public function __construct(array $claims)
    {
        $this->id             = $claims['id'];
        $this->email          = $claims['email'] ?? null;
        $this->name           = $claims['name'] ?? null;
        $this->organizationId = $claims['organization_id'] ?? null;
        $this->roles          = $claims['roles'] ?? [];
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, strict: true);
    }

    // ── Authenticatable contract ──────────────────────────────

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): string
    {
        return $this->id;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): string
    {
        return '';
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return '';
    }
}
