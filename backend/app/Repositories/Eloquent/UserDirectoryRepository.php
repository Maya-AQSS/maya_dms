<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\UserDirectoryRepositoryInterface;
use Illuminate\Support\Facades\DB;

class UserDirectoryRepository implements UserDirectoryRepositoryInterface
{
    /**
     * Busca usuarios por nombre, email o departamento.
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
     * Busca candidatos a revisor con permiso templates.review.
     * 
     * @param string $search
     * @param int $limit
     * @return array
     */
    public function searchTemplateReviewerCandidates(string $search, int $limit, ?string $excludeUserId = null): array
    {
        return $this->searchReviewerCandidatesByPermission('templates.review', $search, $limit, $excludeUserId);
    }

    /**
     * Busca candidatos a revisor con permiso documents.review.
     * 
     * @param string $search
     * @param int $limit
     * @return array
     */
    public function searchDocumentReviewerCandidates(string $search, int $limit, ?string $excludeUserId = null): array
    {
        return $this->searchReviewerCandidatesByPermission('documents.review', $search, $limit, $excludeUserId);
    }

    /**
     * Busca candidatos a revisor con permiso templates.review o documents.review.
     * 
     * @param string $permissionCode
     * @param string $search
     * @param int $limit
     * @return array
     * @return list<array{id: string, name: ?string, email: ?string, role: ?string}>
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
}
