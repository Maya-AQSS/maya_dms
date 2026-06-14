<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Teams\VisibleTeamDto;

interface ApiTeamEmbedServiceInterface
{
    /**
     * Resuelve el equipo visible para una plantilla (o null si no aplica).
     */
    public function resolveTemplateTeam(?string $teamCatalogId, string $viewerUserId): ?VisibleTeamDto;

    /**
     * Resuelve el equipo visible para un documento (o null si no aplica).
     */
    public function resolveDocumentTeam(?string $teamCatalogId, string $viewerUserId): ?VisibleTeamDto;
}
