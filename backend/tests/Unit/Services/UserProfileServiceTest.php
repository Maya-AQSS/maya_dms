<?php

use App\Repositories\Contracts\UserPermissionRepositoryInterface;
use App\Repositories\Contracts\UserProfileRepositoryInterface;
use App\Services\UserProfileService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

// Boot Laravel para que las facades (Cache, Log) resuelvan sus bindings.
uses(Tests\TestCase::class);

beforeEach(function () {
    $this->repository = Mockery::mock(UserProfileRepositoryInterface::class);
    $this->userPermissionRepository = Mockery::mock(UserPermissionRepositoryInterface::class);
    $this->userPermissionRepository->shouldReceive('findPermissionCodesByUserId')->andReturn([])->byDefault();
    $this->userPermissionRepository->shouldReceive('forgetCachedCodesForUser')->byDefault();

    $this->repository->shouldReceive('findStudyTypeIdsByUserId')->andReturn([])->byDefault();
    $this->repository->shouldReceive('findStudyIdsByUserId')->andReturn([])->byDefault();
    $this->repository->shouldReceive('findModuleIdsByUserId')->andReturn([])->byDefault();
    $this->service = new UserProfileService($this->repository, $this->userPermissionRepository);

    $this->jwtProfile = [
        'id'    => 'user-uuid-123',
        'email' => 'test@example.com',
        'name'  => 'Test User',
    ];

    $this->fdwUser = [
        'id'         => 'user-uuid-123',
        'email'      => 'test@example.com',
        'name'       => 'Test User',
        'department' => 'Ingeniería',
    ];

    $this->teams = [
        [
            'id' => 'g1',
            'name' => 'Ingeniería',
            'description' => 'Facultad',
            'role' => 'member',
            'is_department' => false,
        ],
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
        ->shouldReceive('findTeamsByUserId')
        ->once()
        ->with('user-uuid-123')
        ->andReturn($this->teams);

    $this->userPermissionRepository
        ->shouldReceive('findPermissionCodesByUserId')
        ->once()
        ->with('user-uuid-123')
        ->andReturn(['templates.read']);

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
        ->and($profile['study_type_ids'])->toBe([])
        ->and($profile['study_ids'])->toBe([])
        ->and($profile['module_ids'])->toBe([])
        ->and($profile['team_ids'])->toBe([])
        ->and($profile['teams'])->toHaveCount(1)
        ->and($profile['permissions'])->toBe(['templates.read']);
});

// ── Escenario 2: Caché del perfil ──────────────────────────────────────

it('returns cached profile without querying FDW', function () {
    $cachedProfile = [
        'id'             => 'user-uuid-123',
        'email'          => 'test@example.com',
        'name'           => 'Test User',
        'department'     => 'Ingeniería',
        'study_type_ids' => [],
        'study_ids'      => [],
        'module_ids'     => [],
        'team_ids'       => [],
        'permissions'    => [],
        'teams'          => [],
        'source'         => 'fdw',
    ];

    Cache::shouldReceive('get')
        ->once()
        ->with('user_profile:user-uuid-123')
        ->andReturn($cachedProfile);

    // Repository should NOT be called
    $this->repository->shouldNotReceive('findById');
    $this->repository->shouldNotReceive('findTeamsByUserId');

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

    $this->repository->shouldReceive('findTeamsByUserId')
        ->once()
        ->andReturn([]);

    $this->userPermissionRepository
        ->shouldReceive('findPermissionCodesByUserId')
        ->once()
        ->with('user-uuid-123')
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

    $this->userPermissionRepository
        ->shouldReceive('findPermissionCodesByUserId')
        ->once()
        ->with('user-uuid-123')
        ->andReturn([]);

    $profile = $this->service->getProfile('user-uuid-123', $this->jwtProfile);

    expect($profile['source'])->toBe('jwt_fallback')
        ->and($profile['id'])->toBe('user-uuid-123')
        ->and($profile['email'])->toBe('test@example.com')
        ->and($profile['name'])->toBe('Test User')
        ->and($profile['department'])->toBeNull()
        ->and($profile['study_type_ids'])->toBe([])
        ->and($profile['study_ids'])->toBe([])
        ->and($profile['module_ids'])->toBe([])
        ->and($profile['team_ids'])->toBe([])
        ->and($profile['permissions'])->toBe([])
        ->and($profile['teams'])->toBe([]);
});

it('falls back copies department from jwt departamento claim', function () {
    Cache::shouldReceive('get')->once()->andReturnNull();

    $this->repository->shouldReceive('findById')->once()->andReturnNull();

    $this->userPermissionRepository
        ->shouldReceive('findPermissionCodesByUserId')
        ->once()
        ->with('user-uuid-123')
        ->andReturn([]);

    $jwt = array_merge($this->jwtProfile, ['departamento' => 'Desde claim ES']);

    $profile = $this->service->getProfile('user-uuid-123', $jwt);

    expect($profile['department'])->toBe('Desde claim ES');
});

it('falls back copies department from jwt department claim', function () {
    Cache::shouldReceive('get')->once()->andReturnNull();

    $this->repository->shouldReceive('findById')->once()->andReturnNull();

    $this->userPermissionRepository
        ->shouldReceive('findPermissionCodesByUserId')
        ->once()
        ->with('user-uuid-123')
        ->andReturn([]);

    $jwt = array_merge($this->jwtProfile, ['department' => 'Desde claim EN']);

    $profile = $this->service->getProfile('user-uuid-123', $jwt);

    expect($profile['department'])->toBe('Desde claim EN');
});

it('falls back to JWT data when FDW user not found', function () {
    Cache::shouldReceive('get')
        ->once()
        ->andReturnNull();

    $this->repository->shouldReceive('findById')
        ->once()
        ->with('user-uuid-123')
        ->andReturnNull();

    $this->userPermissionRepository
        ->shouldReceive('findPermissionCodesByUserId')
        ->once()
        ->with('user-uuid-123')
        ->andReturn([]);

    $profile = $this->service->getProfile('user-uuid-123', $this->jwtProfile);

    expect($profile['source'])->toBe('jwt_fallback')
        ->and($profile['id'])->toBe('user-uuid-123');
});

// ── Escenario 4: Invalidación de caché ─────────────────────────────────

it('invalidates cache for a specific user', function () {
    $this->userPermissionRepository
        ->shouldReceive('forgetCachedCodesForUser')
        ->once()
        ->with('user-uuid-123');

    Cache::shouldReceive('forget')
        ->once()
        ->with('user_profile:user-uuid-123')
        ->andReturnTrue();

    $this->service->invalidateCache('user-uuid-123');

    // Mockery verifica que Cache::forget fue llamado con la clave correcta
    expect(true)->toBeTrue();
});

// ── Perfil completo incluye datos FDW + JWT + equipos ───────────────────

it('returns empty scope lists when user has no hierarchy assigned in DB', function () {
    Cache::shouldReceive('get')->once()->andReturnNull();

    $this->repository->shouldReceive('findById')->once()->andReturn($this->fdwUser);
    $this->repository->shouldReceive('findTeamsByUserId')->once()->andReturn([]);
    $this->userPermissionRepository->shouldReceive('findPermissionCodesByUserId')->once()->andReturn([]);
    Cache::shouldReceive('put')->once();

    $jwt = array_merge($this->jwtProfile, ['study_id' => 'ST-1', 'module_ids' => json_encode(['M-1', 'M-2'])]);

    $profile = $this->service->getProfile('user-uuid-123', $jwt);

    // Si el usuario no tiene asignaciones, los arrays quedan vacíos.
    expect($profile['study_type_ids'])->toBe([])
        ->and($profile['study_ids'])->toBe([])
        ->and($profile['module_ids'])->toBe([]);
});

it('merges FDW data, JWT claims, and teams into complete profile', function () {
    Cache::shouldReceive('get')->once()->andReturnNull();

    $this->repository->shouldReceive('findById')
        ->once()
        ->andReturn($this->fdwUser);

    $this->repository->shouldReceive('findTeamsByUserId')
        ->once()
        ->andReturn($this->teams);

    $this->userPermissionRepository
        ->shouldReceive('findPermissionCodesByUserId')
        ->once()
        ->with('user-uuid-123')
        ->andReturn([]);

    Cache::shouldReceive('put')->once();

    $profile = $this->service->getProfile('user-uuid-123', $this->jwtProfile);

    expect($profile)
        ->toHaveKeys([
            'id',
            'email',
            'name',
            'department',
            'study_type_ids',
            'study_ids',
            'module_ids',
            'team_ids',
            'permissions',
            'teams',
            'source',
        ])
        ->and($profile['department'])->toBe('Ingeniería')
        ->and($profile['teams'])->toHaveCount(1);
});
