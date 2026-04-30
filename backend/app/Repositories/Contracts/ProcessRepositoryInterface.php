<?php

namespace App\Repositories\Contracts;

interface ProcessRepositoryInterface
{
    /**
     * Lista de procesos ordenados por nombre.
     *
     * @return list<array{id: string, code: string, name: string, alias: string}>
     */
    public function all(): array;
}
