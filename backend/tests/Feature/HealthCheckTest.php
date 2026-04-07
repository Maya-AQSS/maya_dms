<?php

namespace Tests\Feature;

use App\Services\Contracts\HealthCheckServiceInterface;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    // ── Escenario 1: todos los servicios ok ───────────────────────────────────

    public function test_health_returns_200_when_all_services_are_ok(): void
    {
        $this->mock(HealthCheckServiceInterface::class)
            ->shouldReceive('checkAll')
            ->once()
            ->andReturn([
                'status'   => 'ok',
                'services' => [
                    'laravel'   => ['status' => 'ok', 'latency_ms' => 0],
                    'database'  => ['status' => 'ok', 'latency_ms' => 5],
                    'redis'     => ['status' => 'ok', 'latency_ms' => 2],
                    'fdw'       => ['status' => 'ok', 'latency_ms' => 10],
                    'rabbitmq'  => ['status' => 'ok', 'latency_ms' => 3],
                    'websocket' => ['status' => 'ok', 'latency_ms' => 4],
                ],
            ]);

        $this->getJson('/api/v1/health')
            ->assertStatus(200)
            ->assertJson([
                'status'   => 'ok',
                'services' => [
                    'laravel'  => ['status' => 'ok'],
                    'database' => ['status' => 'ok'],
                    'redis'    => ['status' => 'ok'],
                    'fdw'      => ['status' => 'ok'],
                ],
            ]);
    }

    // ── Escenario 2: FDW degradado ────────────────────────────────────────────

    public function test_health_returns_503_when_fdw_is_down(): void
    {
        $this->mock(HealthCheckServiceInterface::class)
            ->shouldReceive('checkAll')
            ->once()
            ->andReturn([
                'status'   => 'degraded',
                'services' => [
                    'laravel'   => ['status' => 'ok',   'latency_ms' => 0],
                    'database'  => ['status' => 'ok',   'latency_ms' => 5],
                    'redis'     => ['status' => 'ok',   'latency_ms' => 2],
                    'fdw'       => ['status' => 'down', 'latency_ms' => null],
                    'rabbitmq'  => ['status' => 'ok',   'latency_ms' => 3],
                    'websocket' => ['status' => 'ok',   'latency_ms' => 4],
                ],
            ]);

        $this->getJson('/api/v1/health')
            ->assertStatus(503)
            ->assertJson([
                'status'   => 'degraded',
                'services' => [
                    'fdw' => ['status' => 'down', 'latency_ms' => null],
                ],
            ]);
    }

    // ── Escenario 3: liveness probe ───────────────────────────────────────────

    public function test_live_always_returns_200_without_checking_dependencies(): void
    {
        $this->getJson('/api/v1/health/live')
            ->assertStatus(200)
            ->assertJson(['status' => 'ok']);
    }

    // ── Escenario 4: readiness probe ──────────────────────────────────────────

    public function test_ready_returns_200_when_database_and_redis_are_ok(): void
    {
        $this->mock(HealthCheckServiceInterface::class)
            ->shouldReceive('checkReadiness')
            ->once()
            ->andReturn([
                'status'   => 'ok',
                'services' => [
                    'database' => ['status' => 'ok', 'latency_ms' => 5],
                    'redis'    => ['status' => 'ok', 'latency_ms' => 2],
                ],
            ]);

        $this->getJson('/api/v1/health/ready')
            ->assertStatus(200)
            ->assertJson(['status' => 'ok']);
    }

    public function test_ready_returns_503_when_a_critical_dependency_is_down(): void
    {
        $this->mock(HealthCheckServiceInterface::class)
            ->shouldReceive('checkReadiness')
            ->once()
            ->andReturn([
                'status'   => 'degraded',
                'services' => [
                    'database' => ['status' => 'down', 'latency_ms' => null],
                    'redis'    => ['status' => 'ok',   'latency_ms' => 2],
                ],
            ]);

        $this->getJson('/api/v1/health/ready')
            ->assertStatus(503)
            ->assertJson([
                'status'   => 'degraded',
                'services' => [
                    'database' => ['status' => 'down', 'latency_ms' => null],
                ],
            ]);
    }

    // ── Escenario 5: sin autenticación ────────────────────────────────────────

    public function test_health_endpoints_do_not_require_authentication(): void
    {
        $mock = $this->mock(HealthCheckServiceInterface::class);
        $mock->shouldReceive('checkAll')
            ->once()
            ->andReturn(['status' => 'ok', 'services' => []]);
        $mock->shouldReceive('checkReadiness')
            ->once()
            ->andReturn(['status' => 'ok', 'services' => []]);

        $this->getJson('/api/v1/health')->assertStatus(200);
        $this->getJson('/api/v1/health/live')->assertStatus(200);
        $this->getJson('/api/v1/health/ready')->assertStatus(200);
    }
}
