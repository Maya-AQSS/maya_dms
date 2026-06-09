<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Smoke tests del controlador `App\Http\Controllers\Api\HealthCheckController`.
 *
 * El runner de checks vive ahora en `Maya\Http\Controllers\AbstractHealthCheckController`
 * del paquete `maya-shared-http-laravel`. La cobertura unitaria de cada
 * implementación de `HealthCheck` (DatabaseHealthCheck, FdwHealthCheck,
 * TcpHealthCheck, …) corresponde a tests del paquete shared.
 *
 * Aquí verificamos exclusivamente:
 *  - El shape de respuesta del controlador concreto de maya_dms.
 *  - Que `/health/live` no toca dependencias externas.
 *  - Que los endpoints son públicos (sin auth JWT).
 */
class HealthCheckTest extends TestCase
{
    public function test_live_always_returns_200_without_touching_dependencies(): void
    {
        $this->getJson('/api/v1/health/live')
            ->assertStatus(200)
            ->assertJson(['status' => 'ok']);
    }

    public function test_health_endpoint_returns_expected_shape(): void
    {
        $response = $this->getJson('/api/v1/health');

        // status puede ser 200 (todo ok) o 503 (algún check error) según el
        // entorno de tests. El shape debe ser consistente en ambos casos.
        $this->assertContains($response->status(), [200, 503]);
        $response->assertJsonStructure([
            'status',
            'checks' => [
                'database',
                'redis',
                'fdw',
                'rabbitmq',
                'websocket',
            ],
        ]);
    }

    public function test_ready_endpoint_returns_expected_shape(): void
    {
        $response = $this->getJson('/api/v1/health/ready');

        $this->assertContains($response->status(), [200, 503]);
        $response->assertJsonStructure([
            'status',
            'checks' => [
                'database',
                'redis',
            ],
        ]);
    }

    public function test_health_endpoints_do_not_require_authentication(): void
    {
        // Sin headers de Authorization deben responder igualmente (200 o 503).
        $this->assertContains($this->getJson('/api/v1/health')->status(), [200, 503]);
        $this->getJson('/api/v1/health/live')->assertStatus(200);
        $this->assertContains($this->getJson('/api/v1/health/ready')->status(), [200, 503]);
    }
}
