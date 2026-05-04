<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Template;
use App\Services\Contracts\ApiTeamEmbedServiceInterface;
use App\Services\Contracts\TeamReadServiceInterface;
use App\Support\ApiEmbeddedTeamResponse;

class ApiTeamEmbedService implements ApiTeamEmbedServiceInterface
{
    public function __construct(
        private readonly TeamReadServiceInterface $teamReadService,
    ) {}

    /**
     * Resuelve el equipo visible para la plantilla y lo deja listo para {@see \App\Http\Resources\TemplateResource}.
     */
    public function embedOnTemplate(Template $template, string $viewerUserId): void
    {
        $teamCatalogId = $template->team_id;

        $team = $this->teamReadService->embeddableTeam(
            $teamCatalogId !== null ? (string) $teamCatalogId : null,
            $viewerUserId,
        );
        $template->setAttribute(ApiEmbeddedTeamResponse::ATTRIBUTE_KEY, $team);
    }

    /**
     * Resuelve el equipo visible para las plantillas y lo deja listo para {@see \App\Http\Resources\TemplateResource}.
     */
    public function embedOnTemplates(iterable $templates, string $viewerUserId): void
    {
        foreach ($templates as $template) {
            $this->embedOnTemplate($template, $viewerUserId);
        }
    }

    /**
     * Resuelve el equipo visible para el documento y lo deja listo para {@see \App\Http\Resources\DocumentResource}.
     */
    public function embedOnDocument(Document $document, string $viewerUserId): void
    {
        $document->loadMissing('template');
        $teamCatalogId = $document->template?->team_id;

        $team = $this->teamReadService->embeddableTeam(
            $teamCatalogId !== null ? (string) $teamCatalogId : null,
            $viewerUserId,
        );

        $document->setAttribute(ApiEmbeddedTeamResponse::ATTRIBUTE_KEY, $team);
    }

    /**
     * Resuelve el equipo visible para los documentos y lo deja listo para {@see \App\Http\Resources\DocumentResource}.
     */
    public function embedOnDocuments(iterable $documents, string $viewerUserId): void
    {
        foreach ($documents as $document) {
            $this->embedOnDocument($document, $viewerUserId);
        }
    }
}
