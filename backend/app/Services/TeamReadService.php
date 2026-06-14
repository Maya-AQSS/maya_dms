<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Teams\VisibleTeamDto;
use App\Repositories\Contracts\TeamReadRepositoryInterface;
use App\Services\Contracts\TeamReadServiceInterface;

class TeamReadService implements TeamReadServiceInterface
{
    public function __construct(
        private readonly TeamReadRepositoryInterface $repository,
    ) {}

    /**
     * Devuelve equipos visibles para el usuario.
     *
     * @return list<VisibleTeamDto>
     */
    public function listVisibleTeamsForUser(string $userId): array
    {
        return array_map(
            static fn (array $row): VisibleTeamDto => VisibleTeamDto::fromRow($row),
            $this->repository->findVisibleTeamsForUser($userId),
        );
    }

    /**
     * Devuelve un equipo visible por ID para el usuario o null.
     */
    public function findVisibleTeamByIdForUser(string $userId, string $teamId): ?VisibleTeamDto
    {
        $row = $this->repository->findVisibleTeamByIdForUser($userId, $teamId);

        return $row !== null ? VisibleTeamDto::fromRow($row) : null;
    }

    /**
     * Valor embebible `team` para respuestas API (sin equipo o sin usuario → null).
     */
    public function embeddableTeam(?string $teamId, string $viewerUserId): ?VisibleTeamDto
    {
        if ($teamId === null || $teamId === '' || $viewerUserId === '') {
            return null;
        }

        return $this->findVisibleTeamByIdForUser($viewerUserId, $teamId);
    }
}
