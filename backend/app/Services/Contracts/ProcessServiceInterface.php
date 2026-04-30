<?php

namespace App\Services\Contracts;

interface ProcessServiceInterface
{
    /**
     * Lista plana de procesos disponibles (top-level + sub-procesos), ordenada por código.
     *
     * @return list<array{id: string, code: string, name: string, alias: string, description: string|null, parent_id: string|null}>
     */
    public function list(): array;
}
