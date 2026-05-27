<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\DTOs\Processes\CreateProcessDto;
use App\DTOs\Processes\UpdateProcessDto;
use App\Models\Process;
use App\Repositories\Contracts\ProcessRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as ConcretePaginator;
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

    public function findModel(string $id): ?Process
    {
        return Process::query()->find($id);
    }

    /**
     * @return array{id: string, code: string, name: string, alias: string, icon: string|null, color: string|null, description: string|null, process_parent_id: string|null}
     */
    public function toRow(Process $process): array
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

    /**
     * @return array{id: string, code: string, name: string, alias: string, icon: string|null, color: string|null, description: string|null, process_parent_id: string|null}
     */
    public function create(CreateProcessDto $dto): array
    {
        $process = Process::query()->create([
            'id' => (string) Str::uuid(),
            'code' => $dto->code,
            'name' => $dto->name,
            'alias' => $dto->alias,
            'description' => $dto->description,
            'process_parent_id' => $dto->processParentId,
        ]);

        return $this->toRow($process);
    }

    /**
     * @return array{id: string, code: string, name: string, alias: string, icon: string|null, color: string|null, description: string|null, process_parent_id: string|null}
     */
    public function update(Process $process, UpdateProcessDto $dto): array
    {
        $process->fill([
            'code' => $dto->code,
            'name' => $dto->name,
            'alias' => $dto->alias,
            'description' => $dto->description,
            'process_parent_id' => $dto->processParentId,
        ]);
        $process->save();

        return $this->toRow($process->fresh() ?? $process);
    }

    public function delete(Process $process): void
    {
        $process->delete();
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

    public function hasDependents(string $processId): bool
    {
        $process = Process::query()->find($processId);

        if ($process === null) {
            return false;
        }

        return $process->children()->exists()
            || $process->templates()->exists()
            || $process->documents()->exists();
    }
}
