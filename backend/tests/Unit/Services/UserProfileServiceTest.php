<?php

use App\Repositories\Contracts\UserProfileRepositoryInterface;
use App\Services\UserProfileService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

// Boot Laravel para que las facades (Cache, Log) resuelvan sus bindings.
uses(Tests\TestCase::class);

beforeEach(function () {
    $this->repository = Mockery::mock(UserProfileRepositoryInterface::class);
    $this->service = new UserProfileService($this->repository);

    $this->jwtProfile = [
        'id'              => 'user-uuid-123',
        'email'           => 'test@example.com',
        'name'            => 'Test User',
        'organization_id' => 'org-1',
        'roles'           => ['docente'],
        'scope'           => 'openid profile',
    ];

    $this->fdwUser = [
        'id'         => 'user-uuid-123',
        'email'      => 'test@example.com',
        'name'       => 'Test User',
        'department' => 'Ingeniería',
    ];

    $this->groups = [
        (object) ['id' => 'g1', 'name' => 'Ingeniería', 'description' => 'Facultad', 'role' => 'member'],
    ];
});

// ── Escenario 1: Consulta filtrada por usuario ─────────────────────────

it('queries FDW filtered by user id from JWT', function () {
    Cache::shouldReceive('get')
        ->once()
        ->with('user_profile:user-uuid-123')
        ->andReturnNull();

    $this->repository
        ->shouldReceive('findById')
        ->once()
        ->with('user-uuid-123')
        ->andReturn($this->fdwUser);

    $this->repository
        ->shouldReceive('findGroupsByUserId')
        ->once()
        ->with('user-uuid-123')
        ->andReturn($this->groups);

    Cache::shouldReceive('put')
        ->once()
        ->withArgs(function ($key, $value, $ttl) {
            return $key === 'user_profile:user-uuid-123' && $ttl === 900;
        });

    $profile = $this->service->getProfile('user-uuid-123', $this->jwtProfile);

    expect($profile['id'])->toBe('user-uuid-123')
        ->and($profile['source'])->toBe('fdw')
        ->and($profile['email'])->toBe('test@example.com')
        ->and($profile['department'])->toBe('Ingeniería')
        ->and($profile['groups'])->toHaveCount(1);
});

// ── Escenario 2: Caché del perfil ──────────────────────────────────────

it('returns cached profile without querying FDW', function () {
    $cachedProfile = [
        'id'              => 'user-uuid-123',
        'email'           => 'test@example.com',
        'name'            => 'Test User',
        'department'      => 'Ingeniería',
        'organization_id' => 'org-1',
        'roles'           => ['docente'],
        'groups'          => [],
        'source'          => 'fdw',
    ];

    Cache::shouldReceive('get')
        ->once()
        ->with('user_profile:user-uuid-123')
        ->andReturn($cachedProfile);

    // Repository should NOT be called
    $this->repository->shouldNotReceive('findById');
    $this->repository->shouldNotReceive('findGroupsByUserId');

    $profile = $this->service->getProfile('user-uuid-123', $this->jwtProfile);

    expect($profile)->toBe($cachedProfile)
        ->and($profile['source'])->toBe('fdw');
});

it('caches profile with key user_profile:{user_id} and 15 min TTL', function () {
    Cache::shouldReceive('get')
        ->once()
        ->with('user_profile:user-uuid-123')
        ->andReturnNull();

    $this->repository->shouldReceive('findById')
        ->once()
        ->with('user-uuid-123')
        ->andReturn($this->fdwUser);

    $this->repository->shouldReceive('findGroupsByUserId')
        ->once()
        ->andReturn([]);

    Cache::shouldReceive('put')
        ->once()
        ->withArgs(function ($key, $value, $ttl) {
            return $key === 'user_profile:user-uuid-123'
                && $ttl === 900  // 15 minutos
                && $value['id'] === 'user-uuid-123';
        });

    $this->service->getProfile('user-uuid-123', $this->jwtProfile);
});

// ── Escenario 3: Fallback ante indisponibilidad FDW ────────────────────

it('falls back to JWT data when FDW throws exception', function () {
    Cache::shouldReceive('get')
        ->once()
        ->andReturnNull();

    $this->repository->shouldReceive('findById')
        ->once()
        ->andThrow(new \Illuminate\Database\QueryException(
            'pgsql',
            'SELECT * FROM users WHERE id = ?',
            ['user-uuid-123'],
            new \Exception('canceling statement due to statement timeout')
        ));

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(function ($message, $context) {
            return str_contains($message, 'FDW query failed')
                && $context['user_id'] === 'user-uuid-123';
        });

    $profile = $this->service->getProfile('user-uuid-123', $this->jwtProfile);

    expect($profile['source'])->toBe('jwt_fallback')
        ->and($profile['id'])->toBe('user-uuid-123')
        ->and($profile['email'])->toBe('test@example.com')
        ->and($profile['name'])->toBe('Test User')
        ->and($profile['roles'])->toBe(['docente'])
        ->and($profile['department'])->toBeNull()
        ->and($profile['groups'])->toBe([]);
});

it('falls back to JWT data when FDW user not found', function () {
    Cache::shouldReceive('get')
        ->once()
        ->andReturnNull();

    $this->repository->shouldReceive('findById')
        ->once()
        ->with('user-uuid-123')
        ->andReturnNull();

    $profile = $this->service->getProfile('user-uuid-123', $this->jwtProfile);

    expect($profile['source'])->toBe('jwt_fallback')
        ->and($profile['id'])->toBe('user-uuid-123');
});

// ── Escenario 4: Invalidación de caché ─────────────────────────────────

it('invalidates cache for a specific user', function () {
    Cache::shouldReceive('forget')
        ->once()
        ->with('user_profile:user-uuid-123')
        ->andReturnTrue();

    $this->service->invalidateCache('user-uuid-123');

    // Mockery verifica que Cache::forget fue llamado con la clave correcta
    expect(true)->toBeTrue();
});

// ── Perfil completo incluye datos FDW + JWT + grupos ───────────────────

it('merges FDW data, JWT claims, and groups into complete profile', function () {
    Cache::shouldReceive('get')->once()->andReturnNull();

    $this->repository->shouldReceive('findById')
        ->once()
        ->andReturn($this->fdwUser);

    $this->repository->shouldReceive('findGroupsByUserId')
        ->once()
        ->andReturn($this->groups);

    Cache::shouldReceive('put')->once();

    $profile = $this->service->getProfile('user-uuid-123', $this->jwtProfile);

    expect($profile)
        ->toHaveKeys([
            'id', 'email', 'name', 'department',
            'organization_id', 'roles', 'groups', 'source',
        ])
        ->and($profile['organization_id'])->toBe('org-1')
        ->and($profile['roles'])->toBe(['docente'])
        ->and($profile['department'])->toBe('Ingeniería')
        ->and($profile['groups'])->toHaveCount(1);
});
