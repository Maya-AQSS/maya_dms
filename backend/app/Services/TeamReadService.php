<?php

namespace App\Services;

use App\Repositories\Contracts\TeamReadRepositoryInterface;
use App\Services\Contracts\TeamReadServiceInterface;

class TeamReadService implements TeamReadServiceInterface
{
    public function __construct(
        private readonly TeamReadRepositoryInterface $repository,
    ) {}

    /**
     * Devuelve equipos visibles para el usuario.
     */
    public function listVisibleTeamsForUser(string $userId): array
    {
        return $this->repository->findVisibleTeamsForUser($userId);
    }

    /**
     * Devuelve un equipo visible por ID para el usuario o null.
     */
    public function findVisibleTeamByIdForUser(string $userId, string $teamId): ?array
    {
        return $this->repository->findVisibleTeamByIdForUser($userId, $teamId);
    }

    /**
     * Valor embebible `team` para respuestas API (sin equipo o sin usuario → null).
     *
     * @return array{id: string, name: string, is_department: bool}|null
     */
    public function embeddableTeam(?string $teamId, string $viewerUserId): ?array
    {
        if ($teamId === null || $teamId === '' || $viewerUserId === '') {
            return null;
        }

        return $this->findVisibleTeamByIdForUser($viewerUserId, $teamId);
    }
}

