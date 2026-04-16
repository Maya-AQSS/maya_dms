<?php

use App\Repositories\Contracts\GroupReadRepositoryInterface;
use App\Services\GroupReadService;

beforeEach(function () {
    $this->repository = Mockery::mock(GroupReadRepositoryInterface::class);
    $this->service = new GroupReadService($this->repository);
});

it('lists visible groups for a user through repository', function () {
    $expected = [
        ['id' => 'g-1', 'name' => 'Grupo A'],
        ['id' => 'g-2', 'name' => 'Grupo B'],
    ];

    $this->repository
        ->shouldReceive('findVisibleGroupsForUser')
        ->once()
        ->with('user-123')
        ->andReturn($expected);

    $result = $this->service->listVisibleGroupsForUser('user-123');

    expect($result)->toBe($expected);
});

it('returns one visible group by id for user', function () {
    $expected = ['id' => 'g-1', 'name' => 'Grupo A'];

    $this->repository
        ->shouldReceive('findVisibleGroupByIdForUser')
        ->once()
        ->with('user-123', 'g-1')
        ->andReturn($expected);

    $result = $this->service->findVisibleGroupByIdForUser('user-123', 'g-1');

    expect($result)->toBe($expected);
});

it('returns null when group is not visible for user', function () {
    $this->repository
        ->shouldReceive('findVisibleGroupByIdForUser')
        ->once()
        ->with('user-123', 'g-missing')
        ->andReturnNull();

    $result = $this->service->findVisibleGroupByIdForUser('user-123', 'g-missing');

    expect($result)->toBeNull();
});

