<?php

declare(strict_types=1);

use App\Models\UserFdw;
use App\Repositories\Eloquent\UserProfileRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Escenario 1 (Consulta filtrada por usuario):
 *   Verifica que findById SIEMPRE filtre por user_id.
 *
 * Escenario 4 (Prohibición de JOIN sin filtro):
 *   Verifica que findTeamsByUserId SIEMPRE incluya filtro de user_id en el JOIN.
 */
uses(TestCase::class);

beforeEach(function () {
    $this->repository = new UserProfileRepository;
});

it('findById runs inside transaction with SET LOCAL statement_timeout and filters by user id', function () {
    DB::shouldReceive('transaction')
        ->once()
        ->andReturnUsing(function ($callback) {
            return $callback();
        });

    DB::shouldReceive('statement')
        ->once()
        ->with('SET LOCAL statement_timeout = 500');

    $mockModel = Mockery::mock('overload:'.UserFdw::class);

    $mockBuilder = Mockery::mock(Builder::class);
    $mockBuilder->shouldReceive('where')
        ->once()
        ->with('id', '=', 'user-123')
        ->andReturnSelf();
    $mockBuilder->shouldReceive('first')
        ->once()
        ->andReturnNull();

    $mockModel->shouldReceive('query')
        ->once()
        ->andReturn($mockBuilder);

    $result = $this->repository->findById('user-123');

    expect($result)->toBeNull();
});

it('findTeamsByUserId always includes user_id filter in JOIN', function () {
    $mockQuery = Mockery::mock();
    $mockQuery->shouldReceive('join')
        ->once()
        ->with('teams', 'teams.id', '=', 'team_members.team_id')
        ->andReturnSelf();
    $mockQuery->shouldReceive('where')
        ->once()
        ->with('team_members.user_id', '=', 'user-123')
        ->andReturnSelf();
    $mockQuery->shouldReceive('whereNull')
        ->once()
        ->with('teams.deleted_at')
        ->andReturnSelf();
    $mockQuery->shouldReceive('select')
        ->once()
        ->andReturnSelf();
    $mockQuery->shouldReceive('get')
        ->once()
        ->andReturn(collect([]));

    DB::shouldReceive('table')
        ->once()
        ->with('team_members')
        ->andReturn($mockQuery);

    $mockConnection = Mockery::mock();
    $mockConnection->shouldReceive('getDriverName')->andReturn('sqlite');
    DB::shouldReceive('connection')->andReturn($mockConnection);

    $result = $this->repository->findTeamsByUserId('user-123');

    expect($result)->toBe([]);
});
