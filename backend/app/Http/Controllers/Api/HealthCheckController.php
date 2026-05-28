<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use Maya\Http\Controllers\AbstractHealthCheckController;
use Maya\Http\Health\DatabaseHealthCheck;
use Maya\Http\Health\HealthCheck;
use Maya\Http\Health\RedisHealthCheck;
use Maya\Http\Health\TcpHealthCheck;

/**
 * Health endpoints de maya_dms.
 *
 *   - GET /health        — checks completos (todos los servicios externos)
 *   - GET /health/live   — liveness (siempre 200)
 *   - GET /health/ready  — readiness (BD + Redis)
 *
 * Extiende {@see AbstractHealthCheckController} del paquete shared
 * `maya-shared-http-laravel`. Cualquier cambio al runner de checks vive
 * allí; aquí solo se declara la composición específica de DMS.
 */
class HealthCheckController extends AbstractHealthCheckController
{
    /**
     * @return array<int, HealthCheck>
     */
    protected function checks(): array
    {
        return [
            new DatabaseHealthCheck,
            new RedisHealthCheck,
            new TcpHealthCheck(
                checkName: 'rabbitmq',
                host: (string) config('services.rabbitmq.host'),
                port: (int) config('services.rabbitmq.port'),
            ),
        ];
    }

    /**
     * Readiness: solo BD y Redis. RabbitMQ y WebSocket pueden estar caídos
     * sin que el pod deje de servir tráfico HTTP sincrónico.
     *
     * @return array<int, HealthCheck>
     */
    protected function readinessChecks(): array
    {
        return [
            new DatabaseHealthCheck,
            new RedisHealthCheck,
        ];
    }
}
