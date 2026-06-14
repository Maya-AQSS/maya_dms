<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Teams\VisibleTeamDto;

interface TeamReadServiceInterface
{
    /**
     * Devuelve equipos visibles para el usuario.
     *
     * @return list<VisibleTeamDto>
     */
    public function listVisibleTeamsForUser(string $userId): array;

    /**
     * Devuelve un equipo visible por ID para el usuario o null.
     */
    public function findVisibleTeamByIdForUser(string $userId, string $teamId): ?VisibleTeamDto;

    /**
     * Valor embebible `team` para respuestas API (sin equipo o sin usuario → null).
     */
    public function embeddableTeam(?string $teamId, string $viewerUserId): ?VisibleTeamDto;
}
