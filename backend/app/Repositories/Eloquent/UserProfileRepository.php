<?php

namespace App\Repositories\Eloquent;

use App\Models\UserFdw;
use App\Repositories\Contracts\UserProfileRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Repositorio de acceso a datos de perfil de usuario vía FDW (vista `users`).
 */
class UserProfileRepository implements UserProfileRepositoryInterface
{
    /**
     * Timeout en milisegundos para la consulta FDW.
     * Si se excede, se lanza QueryException y el servicio usa fallback JWT.
     */
    private const STATEMENT_TIMEOUT_MS = 500;

    /**
     * Obtiene el perfil del usuario desde la vista FDW.
     * SIEMPRE filtra por user_id — nunca ejecuta SELECT sin filtro.
     *
     * @throws \Illuminate\Database\QueryException Si FDW no responde en STATEMENT_TIMEOUT_MS.
     */
    public function findById(string $userId): ?array
    {
        return DB::transaction(function () use ($userId) {
            DB::statement(sprintf('SET LOCAL statement_timeout = %d', self::STATEMENT_TIMEOUT_MS));

            $user = UserFdw::query()
                ->where('id', '=', $userId)
                ->first();

            if ($user === null) {
                return null;
            }

            return [
                'id'         => $user->id,
                'email'      => $user->email,
                'name'       => $user->name,
                'department' => $user->department,
            ];
        });
    }

    /**
     * IDs de tipos de estudio asignados al usuario.
     * SIEMPRE filtra por user_id.
     *
     * @return list<string>
     */
    public function findStudyTypeIdsByUserId(string $userId): array
    {
        return DB::table('user_study_types')
            ->where('user_id', '=', $userId)
            ->pluck('study_type_id')
            ->map(static fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    /**
     * IDs de estudios asignados al usuario.
     * SIEMPRE filtra por user_id.
     *
     * @return list<string>
     */
    public function findStudyIdsByUserId(string $userId): array
    {
        return DB::table('user_studies')
            ->where('user_id', '=', $userId)
            ->pluck('study_id')
            ->map(static fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    /**
     * IDs de módulos de curso asignados al usuario.
     * SIEMPRE filtra por user_id.
     *
     * @return list<string>
     */
    public function findModuleIdsByUserId(string $userId): array
    {
        return DB::table('user_course_modules')
            ->where('user_id', '=', $userId)
            ->pluck('module_id')
            ->map(static fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    /**
     * Obtiene los equipos a los que pertenece el usuario.
     * SIEMPRE filtra por user_id — nunca ejecuta JOIN sin filtro del usuario activo.
     */
    public function findTeamsByUserId(string $userId): array
    {
        return DB::table('team_members')
            ->join('teams', 'teams.id', '=', 'team_members.team_id')
            ->where('team_members.user_id', '=', $userId)
            ->whereNull('teams.deleted_at')
            ->select([
                'teams.id',
                'teams.name',
                'teams.description',
                'team_members.role',
                'teams.is_department',
            ])
            ->get()
            ->map(static fn ($row) => [
                'id' => (string) $row->id,
                'name' => (string) $row->name,
                'description' => $row->description,
                'role' => (string) $row->role,
                'is_department' => (bool) ($row->is_department ?? false),
            ])
            ->values()
            ->all();
    }
}
