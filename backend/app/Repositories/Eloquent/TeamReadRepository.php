<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\TeamReadRepositoryInterface;
use Illuminate\Support\Facades\DB;

class TeamReadRepository implements TeamReadRepositoryInterface
{
    /**
     * Devuelve equipos visibles para el usuario.
     *
     * @return list<array{id: string, name: string, is_department: bool}>
     */
    public function findVisibleTeamsForUser(string $userId): array
    {
        return DB::table('teams')
            ->whereNull('teams.deleted_at')
            ->where(function ($query) use ($userId) {
                $query->where('teams.owner_id', '=', $userId)
                    ->orWhereExists(function ($sub) use ($userId) {
                        $sub->select(DB::raw(1))
                            ->from('team_members')
                            ->whereColumn('team_members.team_id', 'teams.id')
                            ->where('team_members.user_id', '=', $userId);
                    });
            })
            ->select(['teams.id', 'teams.name', 'teams.is_department'])
            ->orderBy('teams.name')
            ->get()
            ->map(fn ($row) => [
                'id' => (string) $row->id,
                'name' => (string) $row->name,
                'is_department' => (bool) ($row->is_department ?? false),
            ])
            ->values()
            ->all();
    }

    /**
     * Devuelve un equipo visible por ID para el usuario o null.
     *
     * @return array{id: string, name: string, is_department: bool}|null
     */
    public function findVisibleTeamByIdForUser(string $userId, string $teamId): ?array
    {
        $row = DB::table('teams')
            ->where('teams.id', '=', $teamId)
            ->whereNull('teams.deleted_at')
            ->where(function ($query) use ($userId) {
                $query->where('teams.owner_id', '=', $userId)
                    ->orWhereExists(function ($sub) use ($userId) {
                        $sub->select(DB::raw(1))
                            ->from('team_members')
                            ->whereColumn('team_members.team_id', 'teams.id')
                            ->where('team_members.user_id', '=', $userId);
                    });
            })
            ->select(['teams.id', 'teams.name', 'teams.is_department'])
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'id' => (string) $row->id,
            'name' => (string) $row->name,
            'is_department' => (bool) ($row->is_department ?? false),
        ];
    }
}

