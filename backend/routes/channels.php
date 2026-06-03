<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channel Routes
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

/**
 * Canal privado de notificaciones por usuario.
 *
 * El {userId} es el Keycloak UUID del destinatario. Solo se autoriza cuando
 * el usuario autenticado (guardado en `id` por el guard jwt-token) coincide
 * con el userId del canal, evitando que un usuario pueda escuchar las
 * notificaciones de otro.
 */
/**
 * Type-hint is `Authenticatable` (not `App\Models\User`) because the JWT
 * middleware from shared-auth-laravel resolves authenticated users to
 * `Maya\Auth\Models\BaseJwtUser` (or `App\Models\JwtUser` in maya_dms) —
 * a DTO of JWT claims, NOT an Eloquent `User`. Strict-typing to `User`
 * caused a TypeError 500 on every `/api/v1/broadcasting/auth` call.
 */
Broadcast::channel('notifications.{userId}', function (Authenticatable $user, string $userId): bool {
    return $user->getAuthIdentifier() === $userId;
});
