<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Template;
use App\Services\Contracts\ApiTeamEmbedServiceInterface;
use App\Services\Contracts\TeamReadServiceInterface;
use App\Support\ApiEmbeddedTeamResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
        $groupId = $template->group_id;

        $team = $this->teamReadService->embeddableTeamForGroup(
            $groupId !== null ? (string) $groupId : null,
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
     * Resuelve el equipo visible para las plantillas paginadas y lo deja listo para {@see \App\Http\Resources\TemplateResource}.
     */
    public function embedOnTemplatePaginator(LengthAwarePaginator $paginator, string $viewerUserId): void
    {
        $this->embedOnTemplates($paginator->items(), $viewerUserId);
    }

    /**
     * Resuelve el equipo visible para el documento y lo deja listo para {@see \App\Http\Resources\DocumentResource}.
     */
    public function embedOnDocument(Document $document, string $viewerUserId): void
    {
        $document->loadMissing('template');
        $groupId = $document->template?->group_id;

        $team = $this->teamReadService->embeddableTeamForGroup(
            $groupId !== null ? (string) $groupId : null,
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
