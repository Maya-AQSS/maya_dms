<?php

namespace App\Repositories\Eloquent;

use App\Models\Process;
use App\Repositories\Contracts\ProcessRepositoryInterface;

class ProcessRepository implements ProcessRepositoryInterface
{
    /**
     * Lista de procesos ordenados por nombre.
     * 
     * @return list<array{id: string, code: string, name: string, alias: string}>
     */
    public function all(): array
    {
        return Process::query()
            ->select(['id', 'code', 'name', 'alias'])
            ->orderBy('name')
            ->get()
            ->map(static fn (Process $process): array => [
                'id' => (string) $process->id,
                'code' => (string) $process->code,
                'name' => (string) $process->name,
                'alias' => (string) $process->alias,
            ])
            ->values()
            ->all();
    }
}
