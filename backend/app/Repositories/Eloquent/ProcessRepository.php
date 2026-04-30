<?php

namespace App\Repositories\Eloquent;

use App\Models\Process;
use App\Repositories\Contracts\ProcessRepositoryInterface;

class ProcessRepository implements ProcessRepositoryInterface
{
    /**
     * Lista plana de procesos ordenados por código (incluye top-level y sub-procesos).
     *
     * El orden por código garantiza que los sub-procesos aparezcan justo
     * después de su padre (PE01, PE01.01, PE01.02, PE02, ...). El frontend
     * agrupa por `parent_id` para mostrar la jerarquía.
     *
     * @return list<array{id: string, code: string, name: string, alias: string, description: string|null, parent_id: string|null}>
     */
    public function all(): array
    {
        return Process::query()
            ->select(['id', 'code', 'name', 'alias', 'description', 'parent_id'])
            ->orderBy('code')
            ->get()
            ->map(static fn (Process $process): array => [
                'id'          => (string) $process->id,
                'code'        => (string) $process->code,
                'name'        => (string) $process->name,
                'alias'       => (string) $process->alias,
                'description' => $process->description,
                'parent_id'   => $process->parent_id,
            ])
            ->values()
            ->all();
    }
}
