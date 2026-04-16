<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\GroupReadRepositoryInterface;
use Illuminate\Support\Facades\DB;

class GroupReadRepository implements GroupReadRepositoryInterface
{
    /**
     * Devuelve grupos visibles para el usuario.
     * 
     * @return list<array{id: string, name: string}>
     */
    public function findVisibleGroupsForUser(string $userId): array
    {
        return DB::table('group_members')
            ->join('groups', 'groups.id', '=', 'group_members.group_id')
            ->where('group_members.user_id', '=', $userId)
            ->whereNull('groups.deleted_at')
            ->select(['groups.id', 'groups.name'])
            ->orderBy('groups.name')
            ->get()
            ->map(fn ($row) => [
                'id' => (string) $row->id,
                'name' => (string) $row->name,
            ])
            ->values()
            ->all();
    }

    /**
     * Devuelve un grupo visible por ID para el usuario o null.
     * 
     * @return array{id: string, name: string}|null
     */
    public function findVisibleGroupByIdForUser(string $userId, string $groupId): ?array
    {
        $row = DB::table('group_members')
            ->join('groups', 'groups.id', '=', 'group_members.group_id')
            ->where('group_members.user_id', '=', $userId)
            ->where('groups.id', '=', $groupId)
            ->whereNull('groups.deleted_at')
            ->select(['groups.id', 'groups.name'])
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'id' => (string) $row->id,
            'name' => (string) $row->name,
        ];
    }
}

