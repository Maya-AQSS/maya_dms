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
     * @return list<array{id: string, code: string, name: string, alias: string, icon: string|null, color: string|null, description: string|null, process_parent_id: string|null}>
     */
    public function list(): array
    {
        return $this->repository->all();
    }

    /**
     * @return array{id: string, code: string, name: string, alias: string, icon: string|null, color: string|null, description: string|null, process_parent_id: string|null}
     */
    public function findOrFail(string $id): array
    {
        $row = $this->repository->find($id);

        if ($row === null) {
            throw (new ModelNotFoundException)->setModel(Process::class, [$id]);
        }

        return $row;
    }

    /**
     * @return array{id: string, code: string, name: string, alias: string, icon: string|null, color: string|null, description: string|null, process_parent_id: string|null}
     */
    public function create(CreateProcessDto $dto): array
    {
        return $this->repository->create($dto);
    }

    /**
     * @return array{id: string, code: string, name: string, alias: string, icon: string|null, color: string|null, description: string|null, process_parent_id: string|null}
     */
    public function update(string $id, UpdateProcessDto $dto): array
    {
        return $this->repository->update($id, $dto);
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
        $this->findOrFail($id);

        if ($this->repository->hasDependents($id)) {
            throw new ConflictHttpException(
                'No se puede eliminar un proceso con subprocesos, plantillas o documentos asociados.',
            );
        }

        $this->repository->delete($id);
    }
}
