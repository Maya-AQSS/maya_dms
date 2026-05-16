<?php
declare(strict_types=1);

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

    /**
     * Comprueba si el usuario pertenece al equipo (vía team_members).
     */
    public function isMember(string $teamId, string $userId): bool;

    /**
     * Mapa `id => name` para los IDs solicitados. Sin filtros de visibilidad —
     * el caller ya restringió la lista a equipos accesibles.
     *
     * @param  list<string>  $teamIds
     * @return array<string, string>
     */
    public function getTeamNamesByIds(array $teamIds): array;
}

