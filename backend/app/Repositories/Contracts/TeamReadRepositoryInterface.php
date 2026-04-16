<?php

namespace App\Repositories\Contracts;

interface TeamReadRepositoryInterface
{
    /**
     * Devuelve equipos visibles para el usuario.
     *
     * @return list<array{id: string, name: string, is_department: bool}>
     */
    public function findVisibleTeamsForUser(string $userId): array;

    /**
     * Devuelve un equipo visible por ID para el usuario o null.
     *
     * @return array{id: string, name: string, is_department: bool}|null
     */
    public function findVisibleTeamByIdForUser(string $userId, string $teamId): ?array;
}

