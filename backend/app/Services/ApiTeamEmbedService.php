<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Services\Contracts\ApiTeamEmbedServiceInterface;
use App\Services\Contracts\TeamReadServiceInterface;
use App\Support\ApiEmbeddedTeamResponse;

class ApiTeamEmbedService implements ApiTeamEmbedServiceInterface
{
    public function __construct(
        private readonly TeamReadServiceInterface $teamReadService,
        private readonly DocumentRepositoryInterface $documentRepository,
    ) {}

    /**
     * Resuelve el equipo visible para una plantilla.
     * Retorna el equipo embedido (array o null) para ser aplicado a la plantilla
     * mediante setAttribute() en el controlador.
     *
     * @return array|null El equipo embedido, listo para ser aplicado a una plantilla
     */
    public function resolveTemplateTeam(?string $teamCatalogId, string $viewerUserId): ?array
    {
        return $this->teamReadService->embeddableTeam(
            $teamCatalogId !== null ? (string) $teamCatalogId : null,
            $viewerUserId,
        );
    }

    /**
     * Resuelve el equipo visible para un documento.
     * Retorna el equipo embedido (array o null) para ser aplicado al documento
     * mediante setAttribute() en el controlador.
     *
     * @return array|null El equipo embedido, listo para ser aplicado a un documento
     */
    public function resolveDocumentTeam(?string $teamCatalogId, string $viewerUserId): ?array
    {
        return $this->teamReadService->embeddableTeam(
            $teamCatalogId !== null ? (string) $teamCatalogId : null,
            $viewerUserId,
        );
    }

    /**
     * @deprecated Use resolveTemplateTeam() instead.
     * Resuelve el equipo visible para la plantilla y lo deja listo para {@see TemplateResource}.
     *
     * @internal Provided for backward compatibility during migration; controllers should call resolveTemplateTeam() instead.
     */
    public function embedOnTemplate($template, string $viewerUserId): void
    {
        // Extract team_id from model without accepting model as type
        $teamCatalogId = $template->team_id ?? null;
        $team = $this->resolveTemplateTeam($teamCatalogId, $viewerUserId);
        $template->setAttribute(ApiEmbeddedTeamResponse::ATTRIBUTE_KEY, $team);
    }

    /**
     * @deprecated Use resolveTemplateTeam() in a loop instead.
     * Resuelve el equipo visible para las plantillas y lo deja listo para {@see TemplateResource}.
     *
     * @internal Provided for backward compatibility during migration.
     */
    public function embedOnTemplates(iterable $templates, string $viewerUserId): void
    {
        foreach ($templates as $template) {
            $this->embedOnTemplate($template, $viewerUserId);
        }
    }

    /**
     * @deprecated Use resolveDocumentTeam() instead.
     * Resuelve el equipo visible para el documento y lo deja listo para {@see DocumentResource}.
     *
     * @internal Provided for backward compatibility during migration; controllers should eagerly load template, extract team_id, call resolveDocumentTeam().
     */
    public function embedOnDocument($document, string $viewerUserId): void
    {
        // Extract team_id from document and its template relation without accepting model as type
        $this->documentRepository->loadTemplate($document);
        $teamCatalogId = $document->team_id ?? $document->template?->team_id ?? null;
        $team = $this->resolveDocumentTeam($teamCatalogId, $viewerUserId);
        $document->setAttribute(ApiEmbeddedTeamResponse::ATTRIBUTE_KEY, $team);
    }

    /**
     * @deprecated Use resolveDocumentTeam() in a loop instead.
     * Resuelve el equipo visible para los documentos y lo deja listo para {@see DocumentResource}.
     *
     * @internal Provided for backward compatibility during migration.
     */
    public function embedOnDocuments(iterable $documents, string $viewerUserId): void
    {
        foreach ($documents as $document) {
            $this->embedOnDocument($document, $viewerUserId);
        }
    }
}
