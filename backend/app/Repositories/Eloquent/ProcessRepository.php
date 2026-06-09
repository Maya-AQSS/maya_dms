<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\DTOs\Processes\CreateProcessDto;
use App\DTOs\Processes\ProcessDto;
use App\DTOs\Processes\UpdateProcessDto;
use App\Models\Process;
use App\Repositories\Contracts\ProcessRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator as ConcretePaginator;
use Illuminate\Support\Str;

class ProcessRepository implements ProcessRepositoryInterface
{
    /**
     * @return list<ProcessDto>
     */
    public function all(): array
    {
        return Process::query()
            ->select(['id', 'code', 'name', 'alias', 'icon', 'color', 'description', 'process_parent_id'])
            ->orderBy('code')
            ->get()
            ->map(fn (Process $process): ProcessDto => ProcessDto::fromModel($process))
            ->values()
            ->all();
    }

    public function find(string $id): ?ProcessDto
    {
        $process = $this->findModel($id);

        return $process !== null ? ProcessDto::fromModel($process) : null;
    }

    public function create(CreateProcessDto $dto): ProcessDto
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

        return ProcessDto::fromModel($process);
    }

    public function update(string $id, UpdateProcessDto $dto): ProcessDto
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

        return ProcessDto::fromModel($process);
    }

    public function delete(string $id): void
    {
        Process::destroy($id);
    }

    public function hasDependents(string $processId): bool
    {
        return Process::query()
            ->whereKey($processId)
            ->where(function ($q) {
                $q->whereHas('children')
                    ->orWhereHas('templates')
                    ->orWhereHas('documents');
            })
            ->exists();
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

        $items = $page->getCollection()->map(fn (Process $p) => ProcessDto::fromModel($p))->values()->all();

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
}
