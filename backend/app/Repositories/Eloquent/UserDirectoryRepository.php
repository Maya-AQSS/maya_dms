<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\DTOs\Users\ReviewerAcademicAssignmentScope;
use App\Repositories\Contracts\UserDirectoryRepositoryInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Directorio de usuarios sobre la vista/tabla `users` y `user_resolved_permissions`.
 *
 * Los candidatos a validador de plantilla o de documento son siempre usuarios que tienen
 * el permiso correspondiente en `user_resolved_permissions`: {@see self::PERMISSION_TEMPLATE_REVIEW}
 * o {@see self::PERMISSION_DOCUMENT_REVIEW} (no se infiere el rol por otro criterio).
 */
class UserDirectoryRepository implements UserDirectoryRepositoryInterface
{
    private const PERMISSION_TEMPLATE_REVIEW = 'template.review';

    private const PERMISSION_DOCUMENT_REVIEW = 'document.review';

    /**
     * Busca usuarios por nombre o email.
     */
    public function searchUsers(string $search, int $limit, ?string $excludeUserId = null): array
    {
        $term = '%'.mb_strtolower($search).'%';

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
     * Usuarios que pueden validar plantillas: tienen {@see self::PERMISSION_TEMPLATE_REVIEW} en `user_resolved_permissions`.
     */
    public function searchTemplateReviewerCandidates(
        string $search,
        int $limit,
        ?string $excludeUserId = null,
        ?ReviewerAcademicAssignmentScope $academicScope = null,
    ): array {
        return $this->searchReviewerCandidatesByPermission(
            self::PERMISSION_TEMPLATE_REVIEW,
            $search,
            $limit,
            $excludeUserId,
            $academicScope,
        );
    }

    /**
     * Usuarios que pueden validar documentos: tienen {@see self::PERMISSION_DOCUMENT_REVIEW} en `user_resolved_permissions`.
     */
    public function searchDocumentReviewerCandidates(
        string $search,
        int $limit,
        ?string $excludeUserId = null,
        ?ReviewerAcademicAssignmentScope $academicScope = null,
    ): array {
        return $this->searchReviewerCandidatesByPermission(
            self::PERMISSION_DOCUMENT_REVIEW,
            $search,
            $limit,
            $excludeUserId,
            $academicScope,
        );
    }

    /**
     * @param  list<string>  $userIds
     * @return list<string>
     */
    public function filterUserIdsMatchingAcademicScope(array $userIds, ReviewerAcademicAssignmentScope $scope): array
    {
        $userIds = array_values(array_unique(array_filter($userIds, static fn (string $id): bool => $id !== '')));

        if ($userIds === [] || $scope->matchesNothing()) {
            return [];
        }

        $query = DB::table('users')->whereIn('users.id', $userIds);
        $this->applyAcademicScopeFilter($query, $scope);

        return $query
            ->pluck('users.id')
            ->map(static fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    /**
     * Candidatos a validador filtrados por un único slug de permiso de revisión
     * resuelto en `user_resolved_permissions` (vista FDW federada con
     * maya_authorization).
     *
     * @param  string  $permissionSlug  p. ej. {@see self::PERMISSION_TEMPLATE_REVIEW} o {@see self::PERMISSION_DOCUMENT_REVIEW}
     * @return list<array{id: string, name: ?string, email: ?string, role: ?string}> `role` reservado para la API (sin datos en `users` FDW)
     */
    private function searchReviewerCandidatesByPermission(
        string $permissionSlug,
        string $search,
        int $limit,
        ?string $excludeUserId = null,
        ?ReviewerAcademicAssignmentScope $academicScope = null,
    ): array {
        $query = DB::table('users')
            ->join('user_resolved_permissions', 'users.id', '=', 'user_resolved_permissions.user_id')
            ->where('user_resolved_permissions.permission_slug', $permissionSlug);

        if ($excludeUserId !== null && $excludeUserId !== '') {
            $query->where('users.id', '!=', $excludeUserId);
        }

        if ($academicScope !== null) {
            $this->applyAcademicScopeFilter($query, $academicScope);
        }

        if (mb_strlen($search) >= 2) {
            $term = '%'.mb_strtolower($search).'%';
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

    private function applyAcademicScopeFilter(Builder $query, ReviewerAcademicAssignmentScope $scope): void
    {
        if ($scope->matchesNothing()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $w) use ($scope): void {
            if ($scope->moduleIds !== []) {
                $w->orWhereExists(function (Builder $sub) use ($scope): void {
                    $sub->select(DB::raw(1))
                        ->from('user_course_modules')
                        ->whereColumn('user_course_modules.user_id', 'users.id')
                        ->whereIn('user_course_modules.module_id', $scope->moduleIds);
                });
            }

            if ($scope->studyIds !== []) {
                $w->orWhereExists(function (Builder $sub) use ($scope): void {
                    $sub->select(DB::raw(1))
                        ->from('user_studies')
                        ->whereColumn('user_studies.user_id', 'users.id')
                        ->whereIn('user_studies.study_id', $scope->studyIds);
                });
            }

            if ($scope->studyTypeIds !== []) {
                $w->orWhereExists(function (Builder $sub) use ($scope): void {
                    $sub->select(DB::raw(1))
                        ->from('user_study_types')
                        ->whereColumn('user_study_types.user_id', 'users.id')
                        ->whereIn('user_study_types.study_type_id', $scope->studyTypeIds);
                });
            }

            if ($scope->teamIds !== []) {
                $w->orWhereExists(function (Builder $sub) use ($scope): void {
                    $sub->select(DB::raw(1))
                        ->from('team_members');
                    if (DB::connection()->getDriverName() === 'pgsql') {
                        $sub->whereRaw('team_members.user_id::text = users.id::text')
                            ->whereIn(DB::raw('team_members.team_id::text'), $scope->teamIds);
                    } else {
                        $sub->whereColumn('team_members.user_id', 'users.id')
                            ->whereIn('team_members.team_id', $scope->teamIds);
                    }
                });
            }
        });
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

    public function findNamesByIds(array $userIds): array
    {
        $ids = array_values(array_unique(array_filter(
            $userIds,
            static fn ($id): bool => is_string($id) && $id !== '',
        )));

        if ($ids === []) {
            return [];
        }

        $names = [];
        foreach (DB::table('users')->whereIn('id', $ids)->pluck('name', 'id') as $id => $name) {
            if (! is_string($name)) {
                continue;
            }

            $trimmed = trim($name);
            if ($trimmed !== '') {
                $names[(string) $id] = $trimmed;
            }
        }

        return $names;
    }
}
