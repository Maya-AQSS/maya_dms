<?php

namespace App\Services\Contracts;

use App\DTOs\Groups\CreateGroupDto;
use App\DTOs\Groups\SyncGroupMembersDto;
use App\DTOs\Groups\UpdateGroupDto;
use App\Models\Group;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface GroupServiceInterface
{
    public function findOrFail(string $id): Group;

    public function paginateWithMembers(int $perPage = 15): LengthAwarePaginator;

    public function create(CreateGroupDto $dto): Group;

    public function update(string $id, UpdateGroupDto $dto): Group;

    public function delete(string $id): void;

    /**
     * @param  list<string>  $userIds
     */
    public function addMembers(string $groupId, array $userIds, string $role = 'member'): void;

    public function removeMember(string $groupId, string $userId): void;

    public function syncMembers(string $groupId, SyncGroupMembersDto $dto, string $role = 'member'): void;
}
