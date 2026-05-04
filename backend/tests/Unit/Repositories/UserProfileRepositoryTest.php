<?php

use App\Repositories\Eloquent\UserProfileRepository;
use Illuminate\Support\Facades\DB;


/**
 * Escenario 1 (Consulta filtrada por usuario):
 *   Verifica que findById SIEMPRE filtre por user_id.
 *
 * Escenario 4 (Prohibición de JOIN sin filtro):
 *   Verifica que findTeamsByUserId SIEMPRE incluya filtro de user_id en el JOIN.
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    $this->repository = new UserProfileRepository();
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

    $mockModel = Mockery::mock('overload:' . \App\Models\UserFdw::class);

    $mockBuilder = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
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
    $mockConnection = Mockery::mock();
    $mockConnection->shouldReceive('getDriverName')->once()->andReturn('sqlite');

    DB::shouldReceive('connection')
        ->once()
        ->andReturn($mockConnection);

    $mockQuery = Mockery::mock();
    $mockQuery->shouldReceive('join')
        ->once()
        ->withArgs(function (string $table, mixed $on, mixed $op = null, mixed $second = null): bool {
            if ($table !== 'teams') {
                return false;
            }
            if ($on instanceof \Closure) {
                return true;
            }

            return $on === 'teams.id' && $op === '=' && $second === 'team_members.team_id';
        })
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

    $result = $this->repository->findTeamsByUserId('user-123');

    expect($result)->toBe([]);
});
