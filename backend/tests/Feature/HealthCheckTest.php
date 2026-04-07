<?php

namespace Tests\Feature;

use App\Services\UserFdwService;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_returns_ok_when_fdw_is_healthy(): void
    {
        $this->mock(UserFdwService::class)
            ->shouldReceive('checkConnectivity')
            ->once()
            ->andReturn(['status' => 'ok', 'latency_ms' => 1.5]);

        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'ok',
                'service' => 'maya-dms',
                'version' => '1.0.0',
                'checks'  => [
                    'fdw' => [
                        'status'     => 'ok',
                        'latency_ms' => 1.5,
                    ],
                ],
            ]);
    }

    public function test_health_returns_503_when_fdw_is_down(): void
    {
        $this->mock(UserFdwService::class)
            ->shouldReceive('checkConnectivity')
            ->once()
            ->andReturn(['status' => 'down', 'latency_ms' => null]);

        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(503)
            ->assertJson([
                'status' => 'degraded',
                'checks' => [
                    'fdw' => [
                        'status'     => 'down',
                        'latency_ms' => null,
                    ],
                ],
            ]);
    }

    public function test_health_does_not_require_authentication(): void
    {
        $this->mock(UserFdwService::class)
            ->shouldReceive('checkConnectivity')
            ->once()
            ->andReturn(['status' => 'ok', 'latency_ms' => 1.0]);

        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200);
    }
}
