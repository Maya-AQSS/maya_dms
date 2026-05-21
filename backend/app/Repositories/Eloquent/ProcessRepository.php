<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\DTOs\Processes\CreateProcessDto;
use App\DTOs\Processes\UpdateProcessDto;
use App\Models\Process;
use App\Repositories\Contracts\ProcessRepositoryInterface;
use Illuminate\Support\Str;

class ProcessRepository implements ProcessRepositoryInterface
{
    /**
     * @return list<array{id: string, code: string, name: string, alias: string, description: string|null, process_parent_id: string|null}>
     */
    public function all(): array
    {
        return Process::query()
            ->select(['id', 'code', 'name', 'alias', 'description', 'process_parent_id'])
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
     * @return array{id: string, code: string, name: string, alias: string, description: string|null, process_parent_id: string|null}
     */
    public function toRow(Process $process): array
    {
        return [
            'id' => (string) $process->id,
            'code' => (string) $process->code,
            'name' => (string) $process->name,
            'alias' => (string) $process->alias,
            'description' => $process->description,
            'process_parent_id' => $process->process_parent_id,
        ];
    }

    /**
     * @return array{id: string, code: string, name: string, alias: string, description: string|null, process_parent_id: string|null}
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
     * @return array{id: string, code: string, name: string, alias: string, description: string|null, process_parent_id: string|null}
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
