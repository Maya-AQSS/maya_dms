<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\DTOs\Processes\CreateProcessDto;
use App\DTOs\Processes\UpdateProcessDto;
use App\Models\Process;

interface ProcessRepositoryInterface
{
    /**
     * @return list<array{id: string, code: string, name: string, alias: string, description: string|null, process_parent_id: string|null}>
     */
    public function all(): array;

    public function findModel(string $id): ?Process;

    /**
     * @return array{id: string, code: string, name: string, alias: string, description: string|null, process_parent_id: string|null}
     */
    public function toRow(Process $process): array;

    /**
     * @return array{id: string, code: string, name: string, alias: string, description: string|null, process_parent_id: string|null}
     */
    public function create(CreateProcessDto $dto): array;

    /**
     * @return array{id: string, code: string, name: string, alias: string, description: string|null, process_parent_id: string|null}
     */
    public function update(Process $process, UpdateProcessDto $dto): array;

    public function delete(Process $process): void;

    public function hasDependents(string $processId): bool;
}
