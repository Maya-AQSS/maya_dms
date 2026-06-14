<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Teams\VisibleTeamDto;
use App\Repositories\Contracts\TeamReadRepositoryInterface;

/**
 * Encapsulates team access authorization logic.
 *
 * Authorization rules:
 * - User can access a team if they are the owner (teams.owner_id) OR a member (team_members).
 * - This service applies those rules before returning team data.
 */
final class TeamAuthorizationService
{
    public function __construct(
        private readonly TeamReadRepositoryInterface $repository,
    ) {}

    /**
     * Check if user has access to a team (owner OR member).
     */
    public function canAccessTeam(string $userId, string $teamId): bool
    {
        $team = $this->repository->findTeamById($teamId);
        if ($team === null) {
            return false;
        }

        // Owner has access
        if ($team['owner_id'] === $userId) {
            return true;
        }

        // Member has access
        return $this->repository->isMember($teamId, $userId);
    }

    /**
     * Get visible teams for user (owner OR member).
     *
     * @return list<VisibleTeamDto>
     */
    public function getVisibleTeams(string $userId): array
    {
        return array_map(
            static fn (array $row): VisibleTeamDto => VisibleTeamDto::fromRow($row),
            $this->repository->findVisibleTeamsForUser($userId),
        );
    }

    /**
     * Get a team if user has access, otherwise null.
     */
    public function getVisibleTeam(string $userId, string $teamId): ?VisibleTeamDto
    {
        $row = $this->repository->findVisibleTeamByIdForUser($userId, $teamId);

        return $row !== null ? VisibleTeamDto::fromRow($row) : null;
    }
}
