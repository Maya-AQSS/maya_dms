<?php

namespace Tests\Unit\Services;

use App\Services\UserFdwService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserFdwServiceTest extends TestCase
{
    private UserFdwService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UserFdwService();
    }

    public function test_find_by_id_returns_user_when_exists(): void
    {
        $user = (object) [
            'id'           => 'user-001',
            'nombre'       => 'Juan Pérez',
            'email'        => 'juan@example.com',
            'departamento' => 'Ingeniería',
        ];

        DB::shouldReceive('table')
            ->with('users')
            ->once()
            ->andReturnSelf();
        DB::shouldReceive('where')
            ->with('id', 'user-001')
            ->once()
            ->andReturnSelf();
        DB::shouldReceive('first')
            ->once()
            ->andReturn($user);

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $result = $this->service->findById('user-001');

        $this->assertNotNull($result);
        $this->assertEquals('user-001', $result->id);
        $this->assertEquals('juan@example.com', $result->email);
    }

    public function test_find_by_id_returns_null_for_missing_user(): void
    {
        DB::shouldReceive('table')->with('users')->once()->andReturnSelf();
        DB::shouldReceive('where')->with('id', 'nonexistent')->once()->andReturnSelf();
        DB::shouldReceive('first')->once()->andReturnNull();

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $result = $this->service->findById('nonexistent');

        $this->assertNull($result);
    }

    public function test_find_by_id_uses_correct_cache_key(): void
    {
        Cache::shouldReceive('remember')
            ->once()
            ->withArgs(function ($key) {
                return $key === 'fdw_user:user-123';
            })
            ->andReturn((object) ['id' => 'user-123']);

        $this->service->findById('user-123');
    }

    public function test_find_by_id_returns_cached_value(): void
    {
        $cached = (object) ['id' => 'user-003', 'nombre' => 'Cached User'];

        Cache::shouldReceive('remember')
            ->once()
            ->andReturn($cached);

        $result = $this->service->findById('user-003');

        $this->assertEquals('Cached User', $result->nombre);
    }

    public function test_invalidate_cache_removes_key(): void
    {
        Cache::shouldReceive('forget')
            ->once()
            ->with('fdw_user:user-004');

        $this->service->invalidateCache('user-004');
    }

    public function test_check_connectivity_returns_ok_when_db_responds(): void
    {
        DB::shouldReceive('selectOne')
            ->with('SELECT 1 FROM users LIMIT 1')
            ->once()
            ->andReturn((object) ['?column?' => 1]);

        $result = $this->service->checkConnectivity();

        $this->assertEquals('ok', $result['status']);
        $this->assertIsFloat($result['latency_ms']);
    }

    public function test_check_connectivity_returns_down_on_exception(): void
    {
        DB::shouldReceive('selectOne')
            ->with('SELECT 1 FROM users LIMIT 1')
            ->once()
            ->andThrow(new \RuntimeException('Connection refused'));

        $result = $this->service->checkConnectivity();

        $this->assertEquals('down', $result['status']);
        $this->assertNull($result['latency_ms']);
    }
}
