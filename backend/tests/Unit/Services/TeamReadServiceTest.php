<?php

use App\Repositories\Contracts\TeamReadRepositoryInterface;
use App\Services\TeamReadService;

beforeEach(function () {
    $this->repository = Mockery::mock(TeamReadRepositoryInterface::class);
    $this->service = new TeamReadService($this->repository);
});

it('lists visible teams for a user through repository', function () {
    $expected = [
        ['id' => 't-1', 'name' => 'Equipo A'],
        ['id' => 't-2', 'name' => 'Equipo B'],
    ];

    $this->repository
        ->shouldReceive('findVisibleTeamsForUser')
        ->once()
        ->with('user-123')
        ->andReturn($expected);

    $result = $this->service->listVisibleTeamsForUser('user-123');

    expect($result)->toBe($expected);
});

it('returns one visible team by id for user', function () {
    $expected = ['id' => 't-1', 'name' => 'Equipo A'];

    $this->repository
        ->shouldReceive('findVisibleTeamByIdForUser')
        ->once()
        ->with('user-123', 't-1')
        ->andReturn($expected);

    $result = $this->service->findVisibleTeamByIdForUser('user-123', 't-1');

    expect($result)->toBe($expected);
});

it('returns null when team is not visible for user', function () {
    $this->repository
        ->shouldReceive('findVisibleTeamByIdForUser')
        ->once()
        ->with('user-123', 't-missing')
        ->andReturnNull();

    $result = $this->service->findVisibleTeamByIdForUser('user-123', 't-missing');

    expect($result)->toBeNull();
});

it('returns null for embeddable team when group id is null without hitting repository', function () {
    $this->repository->shouldNotReceive('findVisibleTeamByIdForUser');

    expect($this->service->embeddableTeamForGroup(null, 'user-123'))->toBeNull();
});

it('returns null for embeddable team when viewer id is empty', function () {
    $this->repository->shouldNotReceive('findVisibleTeamByIdForUser');

    expect($this->service->embeddableTeamForGroup('g-1', ''))->toBeNull();
});

it('delegates embeddable team to findVisibleTeamByIdForUser', function () {
    $expected = ['id' => 'g-1', 'name' => 'Grupo'];

    $this->repository
        ->shouldReceive('findVisibleTeamByIdForUser')
        ->once()
        ->with('user-123', 'g-1')
        ->andReturn($expected);

    expect($this->service->embeddableTeamForGroup('g-1', 'user-123'))->toBe($expected);
});

