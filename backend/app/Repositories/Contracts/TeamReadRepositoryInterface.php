<?php

namespace App\Repositories\Contracts;

interface TeamReadRepositoryInterface
{
    /**
     * Devuelve equipos visibles para el usuario.
     *
     * @return list<array{id: string, name: string}>
     */
    public function findVisibleTeamsForUser(string $userId): array;

    /**
     * Devuelve un equipo visible por ID para el usuario o null.
     *
     * @return array{id: string, name: string}|null
     */
    public function findVisibleTeamByIdForUser(string $userId, string $teamId): ?array;
}

