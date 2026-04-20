<?php

namespace App\Services\Contracts;

interface TeamReadServiceInterface
{
    /**
     * Devuelve equipos visibles para el usuario.
     */
    public function listVisibleTeamsForUser(string $userId): array;

    /**
     * Devuelve un equipo visible por ID para el usuario o null.
     */
    public function findVisibleTeamByIdForUser(string $userId, string $teamId): ?array;

    /**
     * Valor embebible `team` para respuestas API (sin equipo o sin usuario → null).
     *
     * @return array{id: string, name: string, is_department: bool}|null
     */
    public function embeddableTeam(?string $teamId, string $viewerUserId): ?array;
}

