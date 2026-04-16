<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\TeamReadRepositoryInterface;
use Illuminate\Support\Facades\DB;

class TeamReadRepository implements TeamReadRepositoryInterface
{
    /**
     * Devuelve equipos visibles para el usuario.
     *
     * @return list<array{id: string, name: string}>
     */
    public function findVisibleTeamsForUser(string $userId): array
    {
        return DB::table('groups')
            ->whereNull('groups.deleted_at')
            ->where(function ($query) use ($userId) {
                $query->where('groups.owner_id', '=', $userId)
                    ->orWhereExists(function ($sub) use ($userId) {
                        $sub->select(DB::raw(1))
                            ->from('group_members')
                            ->whereColumn('group_members.group_id', 'groups.id')
                            ->where('group_members.user_id', '=', $userId);
                    });
            })
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
     * Devuelve un equipo visible por ID para el usuario o null.
     *
     * @return array{id: string, name: string}|null
     */
    public function findVisibleTeamByIdForUser(string $userId, string $teamId): ?array
    {
        $row = DB::table('groups')
            ->where('groups.id', '=', $teamId)
            ->whereNull('groups.deleted_at')
            ->where(function ($query) use ($userId) {
                $query->where('groups.owner_id', '=', $userId)
                    ->orWhereExists(function ($sub) use ($userId) {
                        $sub->select(DB::raw(1))
                            ->from('group_members')
                            ->whereColumn('group_members.group_id', 'groups.id')
                            ->where('group_members.user_id', '=', $userId);
                    });
            })
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

