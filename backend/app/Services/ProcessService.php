<?php

namespace App\Services;

use App\Repositories\Contracts\ProcessRepositoryInterface;
use App\Services\Contracts\ProcessServiceInterface;

class ProcessService implements ProcessServiceInterface
{
    public function __construct(
        private readonly ProcessRepositoryInterface $repository,
    ) {}

    /**
     * @return list<array{id: string, code: string, name: string, alias: string, description: string|null, parent_id: string|null}>
     */
    public function list(): array
    {
        return $this->repository->all();
    }
}
