<?php

declare(strict_types=1);

namespace App\Notifications\Rules;

use Maya\Messaging\Publishers\NotificationPublisher;

interface ScheduledNotificationRule
{
    /**
     * Evalúa la regla con los parámetros configurados en el dashboard y publica
     * notificaciones según corresponda.
     *
     * @param  array<string, mixed>  $params  Parámetros de la regla (umbral, días, ...)
     * @param  string  $severity  Severidad configurada para la regla
     * @return int Número de notificaciones publicadas
     */
    public function evaluate(NotificationPublisher $publisher, array $params, string $severity): int;
}
