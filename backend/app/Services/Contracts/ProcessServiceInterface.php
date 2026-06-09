<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Processes\CreateProcessDto;
use App\DTOs\Processes\ProcessDto;
use App\DTOs\Processes\UpdateProcessDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProcessServiceInterface
{
    /**
     * @return list<ProcessDto>
     */
    public function list(): array;

    public function findOrFail(string $id): ProcessDto;

    public function create(CreateProcessDto $dto): ProcessDto;

    public function update(string $id, UpdateProcessDto $dto): ProcessDto;

    public function delete(string $id): void;

    /**
     * @param  array{search?: string, parent_id?: string}  $filters
     * @return LengthAwarePaginator<int, ProcessDto>
     */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator;
}
