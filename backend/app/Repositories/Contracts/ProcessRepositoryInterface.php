<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\DTOs\Processes\CreateProcessDto;
use App\DTOs\Processes\UpdateProcessDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProcessRepositoryInterface
{
    /**
     * @return list<array{id: string, code: string, name: string, alias: string, icon: string|null, color: string|null, description: string|null, process_parent_id: string|null}>
     */
    public function all(): array;

    /**
     * @return array{id: string, code: string, name: string, alias: string, icon: string|null, color: string|null, description: string|null, process_parent_id: string|null}|null
     */
    public function find(string $id): ?array;

    /**
     * @return array{id: string, code: string, name: string, alias: string, icon: string|null, color: string|null, description: string|null, process_parent_id: string|null}
     */
    public function create(CreateProcessDto $dto): array;

    /**
     * @return array{id: string, code: string, name: string, alias: string, icon: string|null, color: string|null, description: string|null, process_parent_id: string|null}
     */
    public function update(string $id, UpdateProcessDto $dto): array;

    public function delete(string $id): void;

    public function hasDependents(string $processId): bool;

    /**
     * @param  array{search?: string, parent_id?: string}  $filters
     */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator;
}
