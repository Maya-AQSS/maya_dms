<?php
declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\UserDirectoryRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Directorio de usuarios sobre la vista/tabla `users` y `user_permissions`.
 *
 * Los candidatos a validador de plantilla o de documento son siempre usuarios que tienen
 * el permiso correspondiente en `user_permissions`: {@see self::PERMISSION_TEMPLATE_REVIEW}
 * o {@see self::PERMISSION_DOCUMENT_REVIEW} (no se infiere el rol por otro criterio).
 */
class UserDirectoryRepository implements UserDirectoryRepositoryInterface
{
    private const PERMISSION_TEMPLATE_REVIEW = 'templates.review';

    private const PERMISSION_DOCUMENT_REVIEW = 'documents.review';

    /**
     * Busca usuarios por nombre o email.
     *
     * @param string $search
     * @param int $limit
     * @return array
     */
    public function searchUsers(string $search, int $limit, ?string $excludeUserId = null): array
    {
        $term = '%' . mb_strtolower($search) . '%';

        $query = DB::table('users')
            ->where(function ($query) use ($term) {
                $query->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$term]);
            });

        if ($excludeUserId !== null && $excludeUserId !== '') {
            $query->where('id', '!=', $excludeUserId);
        }

        return $query
            ->select('id', 'name', 'email', 'employee_type')
            ->limit($limit)
            ->get()
            ->map(static fn (object $u): array => [
                'id' => (string) $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->employee_type,
            ])
            ->values()
            ->all();
    }

    /**
     * Usuarios que pueden validar plantillas: tienen {@see self::PERMISSION_TEMPLATE_REVIEW} en `user_permissions`.
     */
    public function searchTemplateReviewerCandidates(string $search, int $limit, ?string $excludeUserId = null): array
    {
        return $this->searchReviewerCandidatesByPermission(self::PERMISSION_TEMPLATE_REVIEW, $search, $limit, $excludeUserId);
    }

    /**
     * Usuarios que pueden validar documentos: tienen {@see self::PERMISSION_DOCUMENT_REVIEW} en `user_permissions`.
     */
    public function searchDocumentReviewerCandidates(string $search, int $limit, ?string $excludeUserId = null): array
    {
        return $this->searchReviewerCandidatesByPermission(self::PERMISSION_DOCUMENT_REVIEW, $search, $limit, $excludeUserId);
    }

    /**
     * Candidatos a validador filtrados por un único código de permiso de revisión ya presente en `user_permissions`.
     *
     * @param string $permissionCode p. ej. {@see self::PERMISSION_TEMPLATE_REVIEW} o {@see self::PERMISSION_DOCUMENT_REVIEW}
     *
     * @return list<array{id: string, name: ?string, email: ?string, role: ?string}> `role` reservado para la API (sin datos en `users` FDW)
     */
    private function searchReviewerCandidatesByPermission(
        string $permissionCode,
        string $search,
        int $limit,
        ?string $excludeUserId = null,
    ): array {
        $query = DB::table('users')
            ->join('user_permissions', 'users.id', '=', 'user_permissions.user_id')
            ->where('user_permissions.permission_code', $permissionCode);

        if ($excludeUserId !== null && $excludeUserId !== '') {
            $query->where('users.id', '!=', $excludeUserId);
        }

        if (mb_strlen($search) >= 2) {
            $term = '%' . mb_strtolower($search) . '%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(users.name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(users.email) LIKE ?', [$term]);
            });
        }

        return $query
            ->select('users.id', 'users.name', 'users.email', 'users.employee_type')
            ->limit($limit)
            ->get()
            ->map(static fn (object $u): array => [
                'id' => (string) $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->employee_type,
            ])
            ->values()
            ->all();
    }

    public function findNameById(string $userId): ?string
    {
        $value = DB::table('users')->where('id', $userId)->value('name');

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
