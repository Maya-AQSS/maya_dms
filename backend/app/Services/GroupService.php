<?php

namespace App\Services;

use App\Models\Group;
use App\Repositories\Contracts\GroupRepositoryInterface;
use App\Services\Contracts\GroupServiceInterface;

class GroupService implements GroupServiceInterface
{
    public function __construct(
        private readonly GroupRepositoryInterface $groupRepository,
    ) {}

    /**
     * Localiza un grupo por su ID o lanza ModelNotFoundException.
     */
    public function findOrFail(string $id): Group
    {
        return $this->groupRepository->findOrFail($id);
    }
}
