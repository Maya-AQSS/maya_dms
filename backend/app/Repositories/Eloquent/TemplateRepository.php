<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\DTOs\Templates\FilterTemplatesDto;
use App\DTOs\Templates\TemplateFilterDto;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Policies\TemplatePolicy;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Support\SearchAccentFold;
use App\Support\TemplateHeadSnapshot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class TemplateRepository implements TemplateRepositoryInterface
{
    /**
     * Localiza una plantilla por su ID o lanza una excepción.
     */
    public function findOrFail(string $id): Template
    {
        return Template::query()
            ->with(['headVersion', 'theme'])
            ->withExists(['comments as has_review_comments' => fn ($q) => $q])
            ->findOrFail($id);
    }

    /**
     * Localiza una plantilla por el ID de una de sus entity_versions o lanza excepción.
     * Sin scope de catálogo; usado para autorización vía Gate en flujo de favoritos.
     */
    public function findOrFailByVersionId(string $entityVersionId): Template
    {
        $versionableId = DB::table('entity_versions')
            ->where('id', '=', $entityVersionId)
            ->where('versionable_type', '=', Template::class)
            ->value('versionable_id');

        if ($versionableId === null) {
            throw (new \Illuminate\Database\Eloquent\ModelNotFoundException())->setModel(Template::class, [$entityVersionId]);
        }

        return Template::query()
            ->withoutGlobalScopes(['user_access'])
            ->with(['headVersion', 'theme'])
            ->findOrFail($versionableId);
    }

    /**
     * Localiza una plantilla por su ID con lock FOR UPDATE o lanza excepción.
     */
    public function findOrFailForUpdate(string $id): Template
    {
        return Template::query()->with('headVersion')->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    /**
     * Igual que {@see self::findOrFail} pero sin el global scope de catálogo `user_access`.
     * Solo para rutas que aplican {@see TemplatePolicy::view} después.
     */
    public function findOrFailWithoutCatalogScope(string $id): Template
    {
        return Template::query()
            ->withoutGlobalScopes(['user_access'])
            ->with(['headVersion', 'theme'])
            ->withExists(['comments as has_review_comments' => fn ($q) => $q])
            ->findOrFail($id);
    }

    /**
     * Plantilla sin scope de catálogo con bloques cargados y ordenados por sort_order.
     * Para definición de bloques de documento cuando no hay snapshot de versión usable.
     */
    public function findOrFailWithBlocksOrderedWithoutCatalogScope(string $id): Template
    {
        return Template::query()
            ->withoutGlobalScopes(['user_access'])
            ->with(['headVersion', 'theme', 'blocks' => fn ($q) => $q->orderBy('sort_order')])
            ->findOrFail($id);
    }

    /**
     * Listado paginado de plantillas con filtros de dominio (ADR-C).
     *
     * Aplica el scope global `user_access` del modelo para garantizar visibilidad.
     *
     * @return LengthAwarePaginator<Template>
     */
    public function paginateFiltered(TemplateFilterDto $filter): LengthAwarePaginator
    {
        $allowedSortColumns = ['updated_at', 'created_at'];
        $sortBy = in_array($filter->sortBy, $allowedSortColumns, true)
            ? 'templates.'.$filter->sortBy
            : 'templates.updated_at';

        $query = Template::withoutGlobalScopes(['join_head_entity_version'])
            ->join('entity_versions as template_head_ev', 'template_head_ev.id', '=', 'templates.head_entity_version_id')
            ->select('templates.*')
            ->addSelect(DB::raw('users.name as author_name'))
            ->leftJoin('users', function ($join) {
                $join->whereRaw(
                    'users.id = '.TemplateHeadSnapshot::jsonTemplateFieldExpression('template_head_ev', 'created_by')
                );
            });

        if ($filter->processId !== null) {
            $query->where('templates.process_id', $filter->processId);
        }

        if ($filter->status !== null) {
            $query->where('template_head_ev.snapshot_data->template->status', $filter->status);
        }

        if ($filter->visibilityLevel !== null) {
            $query->where('template_head_ev.snapshot_data->template->visibility_level', $filter->visibilityLevel);
        }

        if ($filter->usableForDocuments) {
            $query->where('template_head_ev.snapshot_data->template->status', '!=', 'archived')
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('entity_versions as published_ev')
                        ->whereColumn('published_ev.versionable_id', 'templates.id')
                        ->where('published_ev.versionable_type', Template::class)
                        ->where('published_ev.status', 'published')
                        ->where('published_ev.version_number', '>', 0);
                });
        }

        if ($filter->studyTypeId !== null) {
            $query->where('template_head_ev.snapshot_data->template->study_type_id', $filter->studyTypeId);
        }

        if ($filter->studyId !== null) {
            $query->where('template_head_ev.snapshot_data->template->study_id', $filter->studyId);
        }

        if ($filter->moduleId !== null) {
            $query->where('template_head_ev.snapshot_data->template->module_id', $filter->moduleId);
        }

        if ($filter->teamId !== null) {
            $query->where('template_head_ev.snapshot_data->template->team_id', $filter->teamId);
        }

        if ($filter->search !== null && trim($filter->search) !== '') {
            $needle = SearchAccentFold::fold($filter->search);
            if ($needle !== '') {
                [$expr, $tr] = SearchAccentFold::sqlFoldedLowerColumn('users.name');
                $like = '%'.SearchAccentFold::escapeLike($needle).'%';
                $query->whereRaw("{$expr} LIKE ?", [$tr[0], $tr[1], $like]);
            }
        }

        return $query
            ->with(['headVersion'])
            ->withExists(['comments as has_review_comments' => fn ($q) => $q])
            ->with('reviewers')
            ->orderBy($sortBy, $filter->sortDir)
            ->distinct()
            ->paginate($filter->perPage, ['*'], 'page', $filter->page);
    }

    /**
     * Listado con filtros (sin cargar bloques); sin paginación en servidor.
     *
     * @return EloquentCollection<int, Template>
     */
    public function listFiltered(FilterTemplatesDto $filters): Collection
    {
        // Join explícito al cabezal antes que el LEFT JOIN a users: en algunos drivers el orden
        // de JOIN encadenados puede colocar `users` antes de `template_head_ev` y romper la referencia.
        $query = Template::withoutGlobalScopes(['join_head_entity_version'])
            ->join('entity_versions as template_head_ev', 'template_head_ev.id', '=', 'templates.head_entity_version_id')
            ->select('templates.*')
            ->addSelect(DB::raw('users.name as author_name'))
            ->leftJoin('users', function ($join) {
                $join->whereRaw(
                    'users.id = '.TemplateHeadSnapshot::jsonTemplateFieldExpression('template_head_ev', 'created_by')
                );
            });

        if ($filters->visibilityLevel !== null) {
            $query->where('template_head_ev.snapshot_data->template->visibility_level', $filters->visibilityLevel);
        }
        if ($filters->status !== null) {
            $query->where('template_head_ev.snapshot_data->template->status', $filters->status);
        }
        if ($filters->usableForDocuments) {
            $query->where('template_head_ev.snapshot_data->template->status', '!=', 'archived')
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('entity_versions as published_ev')
                        ->whereColumn('published_ev.versionable_id', 'templates.id')
                        ->where('published_ev.versionable_type', Template::class)
                        ->where('published_ev.status', 'published')
                        ->where('published_ev.version_number', '>', 0);
                });
        }
        if ($filters->studyTypeId !== null) {
            $query->where('template_head_ev.snapshot_data->template->study_type_id', $filters->studyTypeId);
        }
        if ($filters->studyId !== null) {
            $query->where('template_head_ev.snapshot_data->template->study_id', $filters->studyId);
        }
        if ($filters->moduleId !== null) {
            $query->where('template_head_ev.snapshot_data->template->module_id', $filters->moduleId);
        }
        if ($filters->teamId !== null) {
            $query->where('template_head_ev.snapshot_data->template->team_id', $filters->teamId);
        }
        if ($filters->authorName !== null && trim($filters->authorName) !== '') {
            $needle = SearchAccentFold::fold($filters->authorName);
            if ($needle !== '') {
                [$expr, $tr] = SearchAccentFold::sqlFoldedLowerColumn('users.name');
                $like = '%'.SearchAccentFold::escapeLike($needle).'%';
                $query->whereRaw("{$expr} LIKE ?", [$tr[0], $tr[1], $like]);
            }
        }
        if ($filters->deliveryDeadline !== null) {
            $deadlineExpr = TemplateHeadSnapshot::jsonTemplateFieldExpression('template_head_ev', 'delivery_deadline');
            $statusExpr = TemplateHeadSnapshot::jsonTemplateFieldExpression('template_head_ev', 'status');
            $cap = $filters->deliveryDeadline;
            // Comparación por prefijo Y-m-d (ISO) para sqlite/mysql/pgsql sin depender de casts de fecha por motor.
            $query->whereRaw(
                "nullif(trim({$deadlineExpr}), '') is not null and substr(trim({$deadlineExpr}), 1, 10) <= ?",
                [$cap],
            );
            // No mezclar publicadas: el plazo de validación no se muestra en UI para ese estado.
            $query->whereRaw("trim({$statusExpr}) <> ?", ['published']);
        }
        if ($filters->publishedOn !== null) {
            $query->whereRaw(
                '(select max(published_at)::date from entity_versions where versionable_id = templates.id and versionable_type = ? and status = ? and version_number > 0) >= ?::date',
                [Template::class, 'published', $filters->publishedOn],
            );
        }
        if ($filters->processId !== null) {
            $query->where('templates.process_id', $filters->processId);
        }

        /** @var EloquentCollection<int, Template> $rows */
        $rows = $query
            ->with(['headVersion'])
            ->withExists([
                'comments as has_review_comments' => fn ($q) => $q,
            ])
            ->with('reviewers')
            ->orderByDesc('templates.updated_at')
            ->distinct()
            ->get();

        return $rows;
    }

    /**
     * Rellena en memoria `latest_published_*` desde `entity_versions` (última versión publicada por plantilla).
     *
     * @param  Collection<int, Template>  $templates
     *                                                {@inheritDoc}
     */
    public function attachLatestPublishedVersionMeta(Collection $templates): void
    {
        if ($templates->isEmpty()) {
            return;
        }

        $ids = $templates->pluck('id')->filter(fn ($id) => is_string($id) && $id !== '')->values()->all();
        if ($ids === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $bindings = array_merge([Template::class], $ids);
        // ROW_NUMBER + CAST: portable entre PostgreSQL (prod) y SQLite (:memory: en tests).
        // Evita DISTINCT ON y ::text, solo soportados en PG.
        $sql = '
            SELECT
                CAST(versionable_id AS TEXT) AS versionable_id,
                CAST(id AS TEXT) AS id,
                version_number,
                snapshot_data,
                published_at
            FROM (
                SELECT
                    versionable_id,
                    id,
                    version_number,
                    snapshot_data,
                    published_at,
                    ROW_NUMBER() OVER (
                        PARTITION BY versionable_id
                        ORDER BY version_number DESC
                    ) AS rn
                FROM entity_versions
                WHERE versionable_type = ?
                  AND versionable_id IN ('.$placeholders.')
                  AND status = \'published\'
                  AND version_number > 0
            ) AS ranked
            WHERE ranked.rn = 1
        ';

        $rows = DB::select($sql, $bindings);

        /** @var array<string, object> $latestByTemplate */
        $latestByTemplate = [];
        foreach ($rows as $row) {
            $templateId = (string) $row->versionable_id;
            $latestByTemplate[$templateId] = $row;
        }

        foreach ($templates as $template) {
            $meta = $latestByTemplate[(string) $template->id] ?? null;
            $template->setAttribute('latest_published_version_id', $meta !== null ? (string) $meta->id : null);
            $template->setAttribute('latest_published_version_number', $meta !== null ? (int) $meta->version_number : null);
            $template->setAttribute('latest_published_name', $meta !== null
                ? $this->extractPublishedTemplateNameFromSnapshotRow($meta->snapshot_data)
                : null);
            $template->setAttribute('latest_published_at', $meta->published_at ?? null);
        }
    }

    private function extractPublishedTemplateNameFromSnapshotRow(mixed $snapshot): ?string
    {
        if (is_string($snapshot) && $snapshot !== '') {
            $decoded = json_decode($snapshot, true);
            if (is_array($decoded)) {
                $snapshot = $decoded;
            }
        }

        if (! is_array($snapshot)) {
            return null;
        }

        $name = data_get($snapshot, 'template.name');
        if (! is_string($name) || trim($name) === '') {
            return null;
        }

        return $name;
    }

    /**
     * Crea una plantilla con los atributos dados.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Template
    {
        return DB::transaction(function () use ($attributes) {
            $template = new Template;
            if (! empty($attributes['id'])) {
                $template->setAttribute('id', $attributes['id']);
            }
            $template->process_id = $attributes['process_id'];
            $template->save();

            $row = array_merge($attributes, [
                'id' => $template->getKey(),
                'status' => $attributes['status'] ?? 'draft',
            ]);

            $snapshot = TemplateHeadSnapshot::buildPayloadFromLegacyRow(
                $row,
                $template->getKey(),
                (string) $template->process_id,
            );

            $now = now();
            $headId = (string) Str::uuid();

            DB::table('entity_versions')->insert([
                'id' => $headId,
                'versionable_type' => Template::class,
                'versionable_id' => $template->getKey(),
                'version_number' => 0,
                'base_version_id' => null,
                'change_set' => null,
                'status' => (string) ($attributes['status'] ?? 'draft'),
                'created_by' => (string) ($attributes['created_by'] ?? ''),
                'published_by' => null,
                'published_at' => null,
                'changelog' => null,
                'snapshot_data' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'is_snapshot_immutable' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $template->head_entity_version_id = $headId;
            $template->save();

            return $template->fresh(['headVersion']);
        });
    }

    /**
     * Actualiza una plantilla con los atributos dados.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Template $template, array $attributes): Template
    {
        if ($attributes === []) {
            return $template->fresh(['headVersion']);
        }

        $delegated = array_flip(TemplateHeadSnapshot::DELEGATED_ATTRIBUTES);
        $headUpdates = array_intersect_key($attributes, $delegated);
        $rest = array_diff_key($attributes, $delegated);

        if ($headUpdates !== []) {
            $template->loadMissing('headVersion');
            $ev = $template->headVersion;
            if ($ev === null) {
                throw new RuntimeException('Plantilla sin versión cabezal en entity_versions.');
            }

            $normalized = $this->normalizeHeadSnapshotUpdates($headUpdates);
            $ev->snapshot_data = TemplateHeadSnapshot::mergeTemplateKey($ev->snapshot_data ?? [], $normalized);
            if (array_key_exists('status', $headUpdates)) {
                $ev->status = (string) $headUpdates['status'];
            }
            $ev->save();
        }

        if ($rest !== []) {
            $template->fill($rest);
            $template->save();
        }

        return $template->fresh(['headVersion']);
    }

    /**
     * @param  array<string, mixed>  $updates
     * @return array<string, mixed>
     */
    private function normalizeHeadSnapshotUpdates(array $updates): array
    {
        $out = [];
        foreach ($updates as $k => $v) {
            $out[$k] = match ($k) {
                'visibility_level' => TemplateHeadSnapshot::normalizeVisibilityForSnapshot($v),
                'delivery_deadline' => TemplateHeadSnapshot::normalizeDeadlineForSnapshot(
                    $v instanceof Carbon ? $v : $v
                ),
                'review_stages' => (int) $v,
                default => $v,
            };
        }

        return $out;
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
     * Inserta bloques en una plantilla desde el JSON de un snapshot publicado (ids de origen ignorados).
     *
     * @param  array<int, array<string, mixed>>  $blocksSnapshot
     */
    public function insertBlocksFromPublishedSnapshot(string $templateId, array $blocksSnapshot): void
    {
        DB::transaction(function () use ($templateId, $blocksSnapshot) {
            foreach ($blocksSnapshot as $block) {
                if (! is_array($block)) {
                    continue;
                }

                $rawTitle = $block['title'] ?? null;
                $title = match (true) {
                    $rawTitle === null => null,
                    is_string($rawTitle) => $rawTitle,
                    is_scalar($rawTitle) => (string) $rawTitle,
                    default => null,
                };

                TemplateBlock::query()->forceCreate([
                    'id' => (string) Str::uuid(),
                    'template_id' => $templateId,
                    'title' => $title,
                    'description' => array_key_exists('description', $block) ? $block['description'] : null,
                    'default_content' => array_key_exists('default_content', $block) ? $block['default_content'] : null,
                    'block_state' => isset($block['block_state']) && is_string($block['block_state'])
                        ? $block['block_state']
                        : 'editable',
                    'sort_order' => isset($block['sort_order']) ? (int) $block['sort_order'] : 0,
                ]);
            }
        });
    }

    /**
     * Carga múltiples plantillas por sus IDs (con el global scope activo), indexadas por ID.
     *
     * @param  list<string>  $ids
     * @return EloquentCollection<string, Template>
     */
    public function findManyByIds(array $ids): EloquentCollection
    {
        $table = (new Template)->getTable();

        return Template::query()->with('headVersion')->whereIn($table.'.id', $ids)->get()->keyBy('id');
    }

    /**
     * Lista plantillas publicadas disponibles para un módulo.
     */
    public function listPublishedByModule(string $moduleId): Collection
    {
        return Template::query()
            ->where('template_head_ev.snapshot_data->template->module_id', $moduleId)
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('entity_versions as published_ev')
                    ->whereColumn('published_ev.versionable_id', 'templates.id')
                    ->where('published_ev.versionable_type', Template::class)
                    ->where('published_ev.status', 'published')
                    ->where('published_ev.version_number', '>', 0);
            })
            ->orderByDesc('templates.updated_at')
            ->get();
    }

    /**
     * Localiza una plantilla para candidatos de revisión documental sin scope de catálogo.
     * Debe incluir relaciones de reviewers y documentReviewers ordenadas.
     */
    public function findForDocumentReviewCandidatesWithoutCatalogScope(string $templateId): ?Template
    {
        return Template::query()
            ->withoutGlobalScopes(['user_access'])
            ->with(['headVersion'])
            ->with([
                'reviewers' => fn ($q) => $q->orderBy('stage'),
                'documentReviewers' => fn ($q) => $q->orderBy('stage')->orderBy('user_id'),
            ])
            ->find($templateId);
    }

    /**
     * Bandeja de revisión de plantillas pendientes para un revisor.
     */
    public function listPendingReviewInboxForUser(string $userId): Collection
    {
        $minPendingByTemplate = DB::table('template_reviewers as tr_min')
            ->select('tr_min.template_id')
            ->selectRaw('MIN(tr_min.stage) as min_stage')
            ->where('tr_min.status', 'pending')
            ->groupBy('tr_min.template_id');

        $deadlineExpr = TemplateHeadSnapshot::jsonTemplateFieldExpression('template_head_ev', 'delivery_deadline');

        $rows = DB::table('template_reviewers')
            ->join('templates', 'templates.id', '=', 'template_reviewers.template_id')
            ->join('entity_versions as template_head_ev', 'template_head_ev.id', '=', 'templates.head_entity_version_id')
            ->leftJoin('users as author_user', function ($join) {
                $join->whereRaw(
                    'author_user.id = '.TemplateHeadSnapshot::jsonTemplateFieldExpression('template_head_ev', 'created_by')
                );
            })
            ->leftJoinSub($minPendingByTemplate, 'ts', function ($join) {
                $join->on('ts.template_id', '=', 'templates.id');
            })
            ->where('template_reviewers.user_id', $userId)
            ->where('template_reviewers.status', 'pending')
            ->whereRaw(TemplateHeadSnapshot::jsonTemplateFieldExpression('template_head_ev', 'status').' = ?', ['in_review'])
            ->where(function ($q) {
                $rm = TemplateHeadSnapshot::jsonTemplateFieldExpression('template_head_ev', 'review_mode');
                $q->whereRaw($rm.' IS NULL')
                    ->orWhereRaw($rm.' = ?', ['parallel'])
                    ->orWhere(function ($q2) {
                        $q2->whereRaw(TemplateHeadSnapshot::jsonTemplateFieldExpression('template_head_ev', 'review_mode').' = ?', ['sequential'])
                            ->whereColumn('template_reviewers.stage', 'ts.min_stage');
                    });
            })
            ->orderByRaw('CASE WHEN '.$deadlineExpr.' IS NULL THEN 1 ELSE 0 END ASC')
            ->orderByRaw($deadlineExpr.' asc')
            ->orderBy('templates.updated_at', 'desc')
            ->get([
                'templates.id',
                'templates.process_id',
                'template_reviewers.stage',
                DB::raw(TemplateHeadSnapshot::jsonTemplateFieldExpression('template_head_ev', 'name').' as name'),
                DB::raw(TemplateHeadSnapshot::jsonTemplateFieldExpression('template_head_ev', 'created_by').' as created_by'),
                DB::raw(TemplateHeadSnapshot::jsonTemplateFieldExpression('template_head_ev', 'delivery_deadline').' as delivery_deadline'),
                DB::raw(TemplateHeadSnapshot::jsonTemplateFieldExpression('template_head_ev', 'status').' as status'),
                'author_user.name as author_name',
            ]);

        $today = Date::today();

        return $rows->map(function (object $row) use ($today): array {
            $deadlineIso = null;
            $daysRemaining = null;
            if ($row->delivery_deadline !== null) {
                $deadline = Date::parse((string) $row->delivery_deadline);
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
