<?php

namespace App\Repositories\Eloquent;

use App\Models\Group;
use App\Models\GroupMember;
use App\Repositories\Contracts\GroupRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class GroupRepository implements GroupRepositoryInterface
{
    /**
     * Localiza un grupo por su ID o lanza ModelNotFoundException.
     */
    public function findOrFail(string $id): Group
    {
        return Group::findOrFail($id);
    }

    /**
     * Listado paginado con miembros cargados (eager load).
     */
    public function paginateWithMembers(int $perPage = 15): LengthAwarePaginator
    {
        return Group::query()
            ->with('members')
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Crea un nuevo grupo.
     */
    public function create(string $name, ?string $description, string $ownerId): Group
    {
        $group = Group::create([
            'name' => $name,
            'description' => $description,
            'owner_id' => $ownerId,
        ]);

        return $group->load('members');
    }

    /**
     * Actualiza un grupo.
     */
    public function update(Group $group, array $attributes): Group
    {
        if ($attributes !== []) {
            $group->update($attributes);
        }

        return $group->fresh(['members']);
    }

    /**
     * Elimina un grupo.
     */
    public function delete(Group $group): void
    {
        DB::transaction(function () use ($group) {
            GroupMember::query()->where('group_id', $group->getKey())->delete();
            $group->delete();
        });
    }

    /**
     * Agrega un miembro a un grupo.
     */
    public function addMember(string $groupId, string $userId, string $role = 'member'): void
    {
        GroupMember::query()->firstOrCreate(
            [
                'group_id' => $groupId,
                'user_id' => $userId,
            ],
            ['role' => $role],
        );
    }

    /**
     * Elimina un miembro de un grupo.
     */
    public function removeMember(string $groupId, string $userId): void
    {
        GroupMember::query()
            ->where('group_id', $groupId)
            ->where('user_id', $userId)
            ->delete();
    }

    /**
     * Sincroniza miembros: elimina los que no están en la lista y crea los faltantes.
     */
    public function syncMembers(string $groupId, array $userIds, string $role = 'member'): void
    {
        $unique = array_values(array_unique($userIds));

        DB::transaction(function () use ($groupId, $unique, $role) {
            GroupMember::query()
                ->where('group_id', $groupId)
                ->whereNotIn('user_id', $unique)
                ->delete();

            foreach ($unique as $userId) {
                $this->addMember($groupId, $userId, $role);
            }
        });
    }
}
