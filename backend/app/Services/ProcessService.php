<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Processes\CreateProcessDto;
use App\DTOs\Processes\ProcessDeletionPreviewDto;
use App\DTOs\Processes\ProcessDto;
use App\DTOs\Processes\UpdateProcessDto;
use App\Models\Process;
use App\Repositories\Contracts\ProcessRepositoryInterface;
use App\Services\Contracts\ProcessServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ProcessService implements ProcessServiceInterface
{
    public function __construct(
        private readonly ProcessRepositoryInterface $repository,
    ) {}

    /**
     * @return list<ProcessDto>
     */
    public function list(): array
    {
        return $this->repository->all();
    }

    public function findOrFail(string $id): ProcessDto
    {
        $row = $this->repository->find($id);

        if ($row === null) {
            throw (new ModelNotFoundException)->setModel(Process::class, [$id]);
        }

        return $row;
    }

    public function create(CreateProcessDto $dto): ProcessDto
    {
        return $this->repository->create($dto);
    }

    public function update(string $id, UpdateProcessDto $dto): ProcessDto
    {
        return $this->repository->update($id, $dto);
    }

    /**
     * @param  array{search?: string, parent_id?: string}  $filters
     * @return LengthAwarePaginator<int, ProcessDto>
     */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->paginate($filters, $perPage);
    }

    public function delete(string $id): void
    {
        $this->findOrFail($id);

        if ($this->repository->hasSubprocesses($id)) {
            throw new ConflictHttpException(
                'No se puede eliminar un proceso con subprocesos. Elimina o reubica primero sus subprocesos.',
            );
        }

        $this->repository->delete($id);
    }

    public function deletionPreview(string $id): ProcessDeletionPreviewDto
    {
        $this->findOrFail($id);

        return ProcessDeletionPreviewDto::fromArray($this->repository->deletionCounts($id));
    }
}
