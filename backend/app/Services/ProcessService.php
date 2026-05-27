<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Processes\CreateProcessDto;
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
     * @return list<array{id: string, code: string, name: string, alias: string, description: string|null, process_parent_id: string|null}>
     */
    public function list(): array
    {
        return $this->repository->all();
    }

    /**
     * @return array{id: string, code: string, name: string, alias: string, description: string|null, process_parent_id: string|null}
     */
    public function findOrFail(string $id): array
    {
        $process = $this->repository->findModel($id);

        if ($process === null) {
            throw (new ModelNotFoundException)->setModel(Process::class, [$id]);
        }

        return $this->repository->toRow($process);
    }

    public function findModelOrFail(string $id): Process
    {
        $process = $this->repository->findModel($id);

        if ($process === null) {
            throw (new ModelNotFoundException)->setModel(Process::class, [$id]);
        }

        return $process;
    }

    /**
     * @return array{id: string, code: string, name: string, alias: string, description: string|null, process_parent_id: string|null}
     */
    public function create(CreateProcessDto $dto): array
    {
        return $this->repository->create($dto);
    }

    /**
     * @return array{id: string, code: string, name: string, alias: string, description: string|null, process_parent_id: string|null}
     */
    public function update(string $id, UpdateProcessDto $dto): array
    {
        $process = $this->findModelOrFail($id);

        return $this->repository->update($process, $dto);
    }

    /**
     * @param  array{search?: string, parent_id?: string}  $filters
     */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->paginate($filters, $perPage);
    }

    public function delete(string $id): void
    {
        if ($this->repository->hasDependents($id)) {
            throw new ConflictHttpException(
                'No se puede eliminar un proceso con subprocesos, plantillas o documentos asociados.',
            );
        }

        $this->repository->delete($this->findModelOrFail($id));
    }
}
