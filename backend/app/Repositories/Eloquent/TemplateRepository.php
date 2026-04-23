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
     * Indica si el usuario es creador o revisor asignado de la plantilla.
     * Usado para control de acceso al historial de auditoría.
     */
    public function isCreatorOrReviewer(string $templateId, string $userId): bool
    {
        $isCreator = DB::table('templates')
            ->where('id', $templateId)
            ->where('created_by', $userId)
            ->exists();

        if ($isCreator) {
            return true;
        }

        return DB::table('template_reviewers')
            ->where('template_id', $templateId)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * Listado paginado con filtros (sin cargar bloques).
     */
    public function paginateFiltered(FilterTemplatesDto $filters, int $perPage = 20): LengthAwarePaginator
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
                'templates.team_id',
                'templates.created_by',
                'templates.status',
                'templates.version',
                'templates.review_stages',
                'templates.review_mode',
                'templates.created_at',
                'templates.updated_at',
            ]);

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

        return $query
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
                'templates.delivery_deadline',
                'templates.status',
                'template_reviewers.stage',
            ]);

        $today = Carbon::today();

        return $rows->map(function (object $row) use ($today): array {
            $deadlineIso = null;
            $daysRemaining = null;
            if ($row->delivery_deadline !== null) {
                $deadline = Carbon::parse((string) $row->delivery_deadline);
                $deadlineIso = $deadline->toIso8601String();
                $daysRemaining = $today->diffInDays($deadline, false);
            }

            return [
                'template_id' => (string) $row->id,
                'title' => (string) $row->name,
                'author_id' => (string) $row->created_by,
                'delivery_deadline' => $deadlineIso,
                'days_remaining' => $daysRemaining,
                'status' => (string) $row->status,
                'review_stage' => (int) $row->stage,
            ];
        })->values();
    }
}
