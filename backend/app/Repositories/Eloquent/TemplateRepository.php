<?php

namespace App\Repositories\Eloquent;

use App\DTOs\Templates\FilterTemplatesDto;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TemplateRepository implements TemplateRepositoryInterface
{
    /**
     * Localiza una plantilla por su ID o lanza una excepción.
     */
    public function findOrFail(string $id): Template
    {
        return Template::query()->findOrFail($id);
    }

    /**
     * Localiza una plantilla por su ID con lock FOR UPDATE o lanza excepción.
     */
    public function findOrFailForUpdate(string $id): Template
    {
        return Template::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    /**
     * Igual que {@see self::findOrFail} pero sin el global scope de catálogo `user_access`.
     * Solo para rutas que aplican {@see \App\Policies\TemplatePolicy::view} después.
     */
    public function findOrFailWithoutCatalogScope(string $id): Template
    {
        return Template::query()
            ->withoutGlobalScopes(['user_access'])
            ->findOrFail($id);
    }

    /**
     * Listado paginado con filtros (sin cargar bloques).
     */
    public function paginateFiltered(FilterTemplatesDto $filters, int $perPage = 10): LengthAwarePaginator
    {
        $query = Template::query()
            ->select([
                'templates.id',
                'templates.name',
                'templates.description',
                'templates.visibility_level',
                'templates.delivery_deadline',
                'templates.study_type_id',
                'templates.study_id',
                'templates.module_id',
                'templates.process_id',
                'templates.team_id',
                'templates.created_by',
                'users.name as author_name',
                'templates.status',
                'templates.version',
                'templates.review_stages',
                'templates.review_mode',
                'templates.created_at',
                'templates.updated_at',
            ])
            ->leftJoin('users', 'users.id', '=', 'templates.created_by');

        if ($filters->visibilityLevel !== null) {
            $query->where('templates.visibility_level', $filters->visibilityLevel);
        }
        if ($filters->status !== null) {
            $query->where('templates.status', $filters->status);
        }
        if ($filters->studyTypeId !== null) {
            $query->where('templates.study_type_id', $filters->studyTypeId);
        }
        if ($filters->studyId !== null) {
            $query->where('templates.study_id', $filters->studyId);
        }
        if ($filters->moduleId !== null) {
            $query->where('templates.module_id', $filters->moduleId);
        }
        if ($filters->teamId !== null) {
            $query->where('templates.team_id', $filters->teamId);
        }
        if ($filters->authorName !== null) {
            $query->where('users.name', 'like', '%'.$filters->authorName.'%');
        }
        if ($filters->deliveryDeadline !== null) {
            $query->whereDate('templates.delivery_deadline', $filters->deliveryDeadline);
        }
        if ($filters->processId !== null) {
            $query->where('templates.process_id', $filters->processId);
        }

        return $query
            ->withExists([
                'comments as has_review_comments' => fn ($q) => $q->where('resolved', false),
            ])
            ->with('reviewers')
            ->orderByDesc('templates.updated_at')
            ->paginate($perPage);
    }

    /**
     * Crea una plantilla con los atributos dados.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Template
    {
        return Template::create($attributes);
    }

    /**
     * Actualiza una plantilla con los atributos dados.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Template $template, array $attributes): Template
    {
        if ($attributes !== []) {
            $template->update($attributes);
        }

        return $template->fresh();
    }

    /**
     * Indica si existe algún documento (incl. borrados en soft delete) asociado a la plantilla.
     * Impide forceDelete por FK restrict.
     */
    public function templateHasDocuments(string $templateId): bool
    {
        return DB::table('documents')
            ->where('template_id', $templateId)
            ->exists();
    }

    /**
     * Replica los bloques de una plantilla origen hacia otra destino.
     */
    public function replicateBlocks(Template $source, Template $target): void
    {
        $source->loadMissing('blocks');

        DB::transaction(function () use ($source, $target) {
            foreach ($source->blocks->sortBy('sort_order') as $block) {
                TemplateBlock::query()->forceCreate([
                    'id' => (string) Str::uuid(),
                    'template_id' => $target->getKey(),
                    'title' => $block->title,
                    'description' => $block->description,
                    'default_content' => $block->default_content,
                    'block_state' => $block->block_state,
                    'sort_order' => $block->sort_order,
                ]);
            }
        });
    }

    /**
     * Carga múltiples plantillas por sus IDs (con el global scope activo), indexadas por ID.
     *
     * @param  list<string>  $ids
     * @return \Illuminate\Database\Eloquent\Collection<string, Template>
     */
    public function findManyByIds(array $ids): \Illuminate\Database\Eloquent\Collection
    {
        return Template::query()->whereIn('id', $ids)->get()->keyBy('id');
    }

    /**
     * Lista plantillas publicadas disponibles para un módulo.
     */
    public function listPublishedByModule(string $moduleId): Collection
    {
        return Template::query()
            ->where('status', 'published')
            ->where('module_id', $moduleId)
            ->orderByDesc('updated_at')
            ->get();
    }

    /**
     * Bandeja de revisión de plantillas pendientes para un revisor.
     */
    public function listPendingReviewInboxForUser(string $userId): Collection
    {
        $rows = DB::table('template_reviewers')
            ->join('templates', 'templates.id', '=', 'template_reviewers.template_id')
            ->leftJoin('users as author_user', 'author_user.id', '=', 'templates.created_by')
            ->where('template_reviewers.user_id', $userId)
            ->where('template_reviewers.status', 'pending')
            ->where('templates.status', 'in_review')
            ->orderByRaw('CASE WHEN templates.delivery_deadline IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('templates.delivery_deadline', 'asc')
            ->orderBy('templates.updated_at', 'desc')
            ->get([
                'templates.id',
                'templates.name',
                'templates.created_by',
                'templates.process_id',
                'templates.delivery_deadline',
                'templates.status',
                'template_reviewers.stage',
                'author_user.name as author_name',
            ]);

        $today = Carbon::today();

        return $rows->map(function (object $row) use ($today): array {
            $deadlineIso = null;
            $daysRemaining = null;
            if ($row->delivery_deadline !== null) {
                $deadline = Carbon::parse((string) $row->delivery_deadline);
                $deadlineIso = $deadline->toIso8601String();
                $daysRemaining = (int) round((float) $today->diffInDays($deadline, false));
            }

            return [
                'template_id' => (string) $row->id,
                'title' => (string) $row->name,
                'author_id' => (string) $row->created_by,
                'process_id' => (string) $row->process_id,
                'author_name' => $row->author_name !== null && $row->author_name !== ''
                    ? (string) $row->author_name
                    : null,
                'delivery_deadline' => $deadlineIso,
                'days_remaining' => $daysRemaining,
                'status' => (string) $row->status,
                'review_stage' => (int) $row->stage,
            ];
        })->values();
    }

    public function transaction(callable $callback): mixed
    {
        return DB::transaction($callback);
    }
}
