<?php

namespace App\Repositories\Contracts;

use App\Models\Group;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface GroupRepositoryInterface
{
    /**
     * Localiza un grupo por su ID o lanza ModelNotFoundException.
     */
    public function findOrFail(string $id): Group;

    /**
     * Listado paginado con miembros cargados (eager load).
     */
    public function paginateWithMembers(int $perPage = 15): LengthAwarePaginator;

    /**
     * Crea un nuevo grupo.
     */
    public function create(string $name, ?string $description, string $ownerId): Group;

    /**
     * Actualiza un grupo.
     */
    public function update(Group $group, array $attributes): Group;

    /**
     * Elimina filas en group_members y aplica soft delete al grupo.
     */
    public function delete(Group $group): void;

    /**
     * Agrega un miembro a un grupo.
     */
    public function addMember(string $groupId, string $userId, string $role = 'member'): void;

    /**
     * Elimina un miembro de un grupo.
     */
    public function removeMember(string $groupId, string $userId): void;

    /**
     * Sincroniza miembros: elimina los que no están en la lista y crea los faltantes.
     *
     * @param  list<string>  $userIds
     */
    public function syncMembers(string $groupId, array $userIds, string $role = 'member'): void;
}
