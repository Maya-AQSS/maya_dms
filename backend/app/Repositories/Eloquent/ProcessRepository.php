<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\DTOs\Processes\CreateProcessDto;
use App\DTOs\Processes\UpdateProcessDto;
use App\Models\Document;
use App\Models\Process;
use App\Models\Template;
use App\Repositories\Contracts\ProcessRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator as ConcretePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProcessRepository implements ProcessRepositoryInterface
{
    /**
     * @return list<array{id: string, code: string, name: string, alias: string, icon: string|null, color: string|null, description: string|null, process_parent_id: string|null}>
     */
    public function all(): array
    {
        return Process::query()
            ->select(['id', 'code', 'name', 'alias', 'icon', 'color', 'description', 'process_parent_id'])
            ->orderBy('code')
            ->get()
            ->map(fn (Process $process): array => $this->toRow($process))
            ->values()
            ->all();
    }

    /**
     * @return array{id: string, code: string, name: string, alias: string, icon: string|null, color: string|null, description: string|null, process_parent_id: string|null}|null
     */
    public function find(string $id): ?array
    {
        $process = $this->findModel($id);

        return $process !== null ? $this->toRow($process) : null;
    }

    /**
     * @return array{id: string, code: string, name: string, alias: string, icon: string|null, color: string|null, description: string|null, process_parent_id: string|null}
     */
    public function create(CreateProcessDto $dto): array
    {
        $process = new Process;
        $process->id = (string) Str::uuid();
        $process->fill([
            'code' => $dto->code,
            'name' => $dto->name,
            'alias' => $dto->alias,
            'description' => $dto->description,
            'process_parent_id' => $dto->processParentId,
            'color' => $dto->color,
            'icon' => $dto->icon,
        ]);
        $process->save();

        return $this->toRow($process);
    }

    /**
     * @return array{id: string, code: string, name: string, alias: string, icon: string|null, color: string|null, description: string|null, process_parent_id: string|null}
     */
    public function update(string $id, UpdateProcessDto $dto): array
    {
        $process = $this->findModel($id);

        if ($process === null) {
            throw new ModelNotFoundException;
        }

        $process->fill([
            'code' => $dto->code,
            'name' => $dto->name,
            'alias' => $dto->alias,
            'description' => $dto->description,
            'process_parent_id' => $dto->processParentId,
            'color' => $dto->color,
            'icon' => $dto->icon,
        ]);
        $process->save();

        return $this->toRow($process);
    }

    /**
     * Soft-delete del proceso y, en cascada, de sus plantillas y documentos.
     *
     * No se borran las filas hijas de cada plantilla/documento (bloques, versiones,
     * comentarios, etc.): quedan accesibles solo a través del padre soft-deleted,
     * que es la semántica habitual de soft delete en el proyecto.
     */
    public function delete(string $id): void
    {
        DB::transaction(function () use ($id): void {
            $this->templatesQueryForProcess($id)
                ->get()
                ->each(fn (Template $template) => $template->delete());

            $this->documentsQueryForProcess($id)
                ->get()
                ->each(fn (Document $document) => $document->delete());

            Process::query()->whereKey($id)->first()?->delete();
        });
    }

    public function hasSubprocesses(string $processId): bool
    {
        return Process::query()
            ->where('process_parent_id', $processId)
            ->exists();
    }

    /**
     * Conteo de dependientes afectados por el borrado, sobre TODAS las filas
     * (sin scopes de visibilidad por usuario), porque el borrado las afecta a todas.
     *
     * @return array{templates_count: int, documents_count: int, subprocess_count: int}
     */
    public function deletionCounts(string $processId): array
    {
        return [
            'templates_count' => $this->templatesQueryForProcess($processId)->count(),
            'documents_count' => $this->documentsQueryForProcess($processId)->count(),
            'subprocess_count' => Process::query()
                ->where('process_parent_id', $processId)
                ->count(),
        ];
    }

    /**
     * Plantillas del proceso sin los scopes de visibilidad/join de cabezal
     * (mantiene el scope de soft delete para excluir las ya eliminadas).
     *
     * @return \Illuminate\Database\Eloquent\Builder<Template>
     */
    private function templatesQueryForProcess(string $processId)
    {
        return Template::withoutGlobalScopes(['join_head_entity_version', 'user_access'])
            ->where('process_id', $processId);
    }

    /**
     * Documentos del proceso sin los scopes de visibilidad/join de cabezal
     * (mantiene el scope de soft delete para excluir los ya eliminados).
     *
     * @return \Illuminate\Database\Eloquent\Builder<Document>
     */
    private function documentsQueryForProcess(string $processId)
    {
        return Document::withoutGlobalScopes(['join_head_document_entity_version', 'user_access'])
            ->where('process_id', $processId);
    }

    /**
     * @param  array{search?: string, parent_id?: string}  $filters
     */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = Process::query()
            ->select(['id', 'code', 'name', 'alias', 'icon', 'color', 'description', 'process_parent_id'])
            ->orderBy('code');

        if (! empty($filters['search'])) {
            $needle = '%'.$filters['search'].'%';
            $query->where(function ($w) use ($needle) {
                $w->where('name', 'ilike', $needle)
                    ->orWhere('code', 'ilike', $needle)
                    ->orWhere('alias', 'ilike', $needle);
            });
        }

        if (isset($filters['parent_id']) && $filters['parent_id'] !== '') {
            if ($filters['parent_id'] === 'root') {
                $query->whereNull('process_parent_id');
            } else {
                $query->where('process_parent_id', $filters['parent_id']);
            }
        }

        /** @var LengthAwarePaginator<int, Process> $page */
        $page = $query->paginate($perPage);

        $items = $page->getCollection()->map(fn (Process $p) => $this->toRow($p))->values()->all();

        return new ConcretePaginator(
            items: $items,
            total: $page->total(),
            perPage: $page->perPage(),
            currentPage: $page->currentPage(),
            options: [
                'path' => $page->path(),
                'pageName' => $page->getPageName(),
            ],
        );
    }

    private function findModel(string $id): ?Process
    {
        return Process::query()->find($id);
    }

    /**
     * @return array{id: string, code: string, name: string, alias: string, icon: string|null, color: string|null, description: string|null, process_parent_id: string|null}
     */
    private function toRow(Process $process): array
    {
        return [
            'id' => (string) $process->id,
            'code' => (string) $process->code,
            'name' => (string) $process->name,
            'alias' => (string) $process->alias,
            'icon' => $process->icon,
            'color' => $process->color,
            'description' => $process->description,
            'process_parent_id' => $process->process_parent_id,
        ];
    }
}
