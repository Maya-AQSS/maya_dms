<?php
declare(strict_types=1);

namespace App\Repositories\Contracts;

interface ProcessRepositoryInterface
{
    /**
     * Lista plana de procesos ordenados por código (incluye top-level y sub-procesos).
     *
     * @return list<array{id: string, code: string, name: string, alias: string, description: string|null, process_parent_id: string|null}>
     */
    public function all(): array;
}
