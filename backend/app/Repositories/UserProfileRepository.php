<?php

namespace App\Repositories;

use App\Models\UserFdw;
use Illuminate\Support\Facades\DB;

/**
 * Repositorio de acceso a datos de usuario vía FDW.
 *
 * Escenarios cubiertos:
 *   - Escenario 1: findById() SIEMPRE filtra por WHERE id = :user_id.
 *   - Escenario 3: statement_timeout de 500 ms permite que el servicio detecte
 *                   indisponibilidad FDW y aplique fallback JWT.
 *   - Escenario 4: findGroupsByUserId() incluye filtro user_id en el JOIN;
 *                   no existe JOIN sin filtro del usuario activo.
 *   - Requisito de rendimiento: SET LOCAL statement_timeout = 500 garantiza
 *                   que la consulta sin caché aborta antes de 500 ms.
 */
class UserProfileRepository
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
        // Aplicar timeout a nivel de sentencia para detectar FDW lento
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
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'username'   => $user->username,
            'is_active'  => $user->is_active,
        ];
    }

    /**
     * Obtiene los grupos académicos a los que pertenece el usuario.
     * SIEMPRE filtra por user_id — nunca ejecuta JOIN sin filtro del usuario activo.
     */
    public function findGroupsByUserId(string $userId): array
    {
        return DB::table('group_members')
            ->join('groups', 'groups.id', '=', 'group_members.group_id')
            ->where('group_members.user_id', '=', $userId)
            ->whereNull('groups.deleted_at')
            ->select([
                'groups.id',
                'groups.name',
                'groups.description',
                'group_members.role',
            ])
            ->get()
            ->toArray();
    }
}
