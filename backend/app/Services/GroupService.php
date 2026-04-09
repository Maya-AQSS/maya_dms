<?php

namespace App\Services;

use App\DTOs\Groups\CreateGroupDto;
use App\DTOs\Groups\SyncGroupMembersDto;
use App\DTOs\Groups\UpdateGroupDto;
use App\Models\Group;
use App\Repositories\Contracts\GroupRepositoryInterface;
use App\Services\Contracts\GroupServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

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

    /**
     * Listado paginado con miembros cargados (eager load).
     */
    public function paginateWithMembers(int $perPage = 15): LengthAwarePaginator
    {
        return $this->groupRepository->paginateWithMembers($perPage);
    }

    /**
     * Crea un nuevo grupo.
     */
    public function create(CreateGroupDto $dto): Group
    {
        $ownerId = Auth::id();
        if ($ownerId === null) {
            throw new \RuntimeException('Cannot create group without authenticated user.');
        }

        return $this->groupRepository->create(
            $dto->name,
            $dto->description,
            (string) $ownerId,
        );
    }

    /**
     * Actualiza un grupo.
     */
    public function update(string $id, UpdateGroupDto $dto): Group
    {
        $group = $this->groupRepository->findOrFail($id);

        $attributes = [];
        if ($dto->name !== null) {
            $attributes['name'] = $dto->name;
        }
        if ($dto->description !== null) {
            $attributes['description'] = $dto->description;
        }

        return $this->groupRepository->update($group, $attributes);
    }

    /**
     * Elimina un grupo.
     */
    public function delete(string $id): void
    {
        $group = $this->groupRepository->findOrFail($id);
        $this->groupRepository->delete($group);
    }

    /**
     * Agrega miembros a un grupo.
     */
    public function addMembers(string $groupId, array $userIds, string $role = 'member'): void
    {
        $this->groupRepository->findOrFail($groupId);

        foreach (array_unique($userIds) as $userId) {
            $this->groupRepository->addMember($groupId, (string) $userId, $role);
        }
    }

    /**
     * Elimina un miembro de un grupo.
     */
    public function removeMember(string $groupId, string $userId): void
    {
        $this->groupRepository->findOrFail($groupId);
        $this->groupRepository->removeMember($groupId, $userId);
    }

    /**
     * Sincroniza miembros: elimina los que no están en la lista y crea los faltantes.
     */
    public function syncMembers(string $groupId, SyncGroupMembersDto $dto, string $role = 'member'): void
    {
        $this->groupRepository->findOrFail($groupId);
        $this->groupRepository->syncMembers($groupId, $dto->userIds, $role);
    }
}
