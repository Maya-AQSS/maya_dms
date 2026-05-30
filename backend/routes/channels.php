<?php

declare(strict_types=1);

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
Broadcast::channel('notifications.{userId}', function (\App\Models\User $user, string $userId): bool {
    return $user->id === $userId;
});
