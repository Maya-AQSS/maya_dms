<?php

namespace App\Services\Contracts;

interface ProcessServiceInterface
{
    /**
     * Lista de procesos disponibles.
     *
     * @return list<array{id: string, code: string, name: string, alias: string}>
     */
    public function list(): array;
}
