<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\TeamReadRepositoryInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class TeamReadRepository implements TeamReadRepositoryInterface
{
    /**
     * En PostgreSQL el catálogo `teams` puede usar `uuid` para `id`; los bindings de Laravel
     * llegan como texto y sin cast explícito falla `uuid = character varying`.
     */
    private function whereTeamIdMatches(Builder $query, string $qualifiedTeamIdColumn, string $teamId): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            $query->whereRaw($qualifiedTeamIdColumn.'::text = ?::text', [$teamId]);

            return;
        }

        $query->where($qualifiedTeamIdColumn, '=', $teamId);
    }

    /**
     * Normaliza comparación de user_id entre catálogos FDW (`varchar`) y tablas locales (`uuid`).
     */
    private function whereUserIdMatches(Builder $query, string $qualifiedUserIdColumn, string $userId): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            $query->whereRaw($qualifiedUserIdColumn.'::text = ?::text', [$userId]);

            return;
        }

        $query->where($qualifiedUserIdColumn, '=', $userId);
    }

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
                $this->whereUserIdMatches($query, 'teams.owner_id', $userId);
                $query
                    ->orWhereExists(function ($sub) use ($userId) {
                        $sub->select(DB::raw(1))
                            ->from('team_members');
                        if (DB::connection()->getDriverName() === 'pgsql') {
                            $sub->whereRaw('team_members.team_id::text = teams.id::text');
                            $sub->whereRaw('team_members.user_id::text = ?::text', [$userId]);
                        } else {
                            $sub->whereColumn('team_members.team_id', 'teams.id');
                            $sub->where('team_members.user_id', '=', $userId);
                        }
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
            ->tap(fn (Builder $q) => $this->whereTeamIdMatches($q, 'teams.id', $teamId))
            ->whereNull('teams.deleted_at')
            ->where(function ($query) use ($userId) {
                $this->whereUserIdMatches($query, 'teams.owner_id', $userId);
                $query
                    ->orWhereExists(function ($sub) use ($userId) {
                        $sub->select(DB::raw(1))
                            ->from('team_members');
                        if (DB::connection()->getDriverName() === 'pgsql') {
                            $sub->whereRaw('team_members.team_id::text = teams.id::text');
                            $sub->whereRaw('team_members.user_id::text = ?::text', [$userId]);
                        } else {
                            $sub->whereColumn('team_members.team_id', 'teams.id');
                            $sub->where('team_members.user_id', '=', $userId);
                        }
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

