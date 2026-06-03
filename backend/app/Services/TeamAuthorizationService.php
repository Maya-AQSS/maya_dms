<?php

declare(strict_types=1);

namespace App\Services;

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
     * @return list<array{id: string, name: string, is_department: bool}>
     */
    public function getVisibleTeams(string $userId): array
    {
        return $this->repository->findVisibleTeamsForUser($userId);
    }

    /**
     * Get a team if user has access, otherwise null.
     *
     * @return array{id: string, name: string, is_department: bool}|null
     */
    public function getVisibleTeam(string $userId, string $teamId): ?array
    {
        return $this->repository->findVisibleTeamByIdForUser($userId, $teamId);
    }
}
