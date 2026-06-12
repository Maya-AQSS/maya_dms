<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Processes\CreateProcessDto;
use App\DTOs\Processes\ProcessDeletionPreviewDto;
use App\DTOs\Processes\ProcessDto;
use App\DTOs\Processes\UpdateProcessDto;
use Maya\Http\Pagination\PaginatedDto;

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
     * Conteo de dependientes afectados por el borrado del proceso.
     */
    public function deletionPreview(string $id): ProcessDeletionPreviewDto;

    /**
     * Listado paginado con el envelope plano estándar (ADR-C).
     *
     * @param  array{search?: string, parent_id?: string, sort_by?: string, sort_dir?: string}  $filters
     * @return PaginatedDto<ProcessDto>
     */
    public function paginate(array $filters, int $perPage = 20): PaginatedDto;
}
