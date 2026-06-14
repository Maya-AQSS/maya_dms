<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Teams\VisibleTeamDto;
use App\Services\Contracts\ApiTeamEmbedServiceInterface;
use App\Services\Contracts\TeamReadServiceInterface;

/**
 * Resuelve el equipo visible aplicable a una plantilla o documento, sin tocar
 * el modelo Eloquent: devuelve un VisibleTeamDto (o null) que la capa HTTP
 * adjunta a la respuesta. La mutación transitoria del modelo (atributo de
 * presentación previo al Resource) la realiza el controlador vía el concern
 * App\Http\Concerns\ResolvesApiEmbeddedTeam.
 */
class ApiTeamEmbedService implements ApiTeamEmbedServiceInterface
{
    public function __construct(
        private readonly TeamReadServiceInterface $teamReadService,
    ) {}

    /**
     * Resuelve el equipo visible para una plantilla (o null si no aplica).
     */
    public function resolveTemplateTeam(?string $teamCatalogId, string $viewerUserId): ?VisibleTeamDto
    {
        return $this->teamReadService->embeddableTeam(
            $teamCatalogId !== null ? (string) $teamCatalogId : null,
            $viewerUserId,
        );
    }

    /**
     * Resuelve el equipo visible para un documento (o null si no aplica).
     */
    public function resolveDocumentTeam(?string $teamCatalogId, string $viewerUserId): ?VisibleTeamDto
    {
        return $this->teamReadService->embeddableTeam(
            $teamCatalogId !== null ? (string) $teamCatalogId : null,
            $viewerUserId,
        );
    }
}
