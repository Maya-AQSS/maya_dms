<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Http\Resources\DocumentResource;
use App\Http\Resources\TemplateResource;
use App\Models\Document;
use App\Models\Template;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Services\Contracts\ApiTeamEmbedServiceInterface;
use App\Support\ApiEmbeddedTeamResponse;

/**
 * Adjunta el equipo visible resuelto al atributo de presentación transitorio
 * del modelo, justo antes de mapearlo a {@see TemplateResource}
 * / {@see DocumentResource}.
 *
 * La resolución del equipo vive en {@see ApiTeamEmbedServiceInterface} (capa
 * Service, sin mutar Eloquent); esta mutación de presentación pertenece a la
 * capa HTTP (controlador), que es la que da forma a la respuesta.
 *
 * Requiere que el controlador exponga un {@see ApiTeamEmbedServiceInterface}
 * vía la propiedad `$apiTeamEmbedService`.
 */
trait ResolvesApiEmbeddedTeam
{
    /**
     * Resuelve y adjunta el equipo aplicable a una plantilla.
     */
    protected function applyEmbeddedTeamToTemplate(Template $template, string $viewerUserId): void
    {
        $team = $this->apiTeamEmbedService->resolveTemplateTeam(
            $template->team_id !== null ? (string) $template->team_id : null,
            $viewerUserId,
        );

        $template->setAttribute(ApiEmbeddedTeamResponse::ATTRIBUTE_KEY, $team?->toArray());
    }

    /**
     * Resuelve y adjunta el equipo aplicable a cada plantilla de la colección.
     *
     * @param  iterable<Template>  $templates
     */
    protected function applyEmbeddedTeamToTemplates(iterable $templates, string $viewerUserId): void
    {
        foreach ($templates as $template) {
            $this->applyEmbeddedTeamToTemplate($template, $viewerUserId);
        }
    }

    /**
     * Resuelve y adjunta el equipo aplicable a un documento (deriva el team del
     * documento o de su plantilla).
     */
    protected function applyEmbeddedTeamToDocument(Document $document, string $viewerUserId): void
    {
        app(DocumentRepositoryInterface::class)->loadTemplate($document);
        $teamCatalogId = $document->team_id ?? $document->template?->team_id ?? null;

        $team = $this->apiTeamEmbedService->resolveDocumentTeam(
            $teamCatalogId !== null ? (string) $teamCatalogId : null,
            $viewerUserId,
        );

        $document->setAttribute(ApiEmbeddedTeamResponse::ATTRIBUTE_KEY, $team?->toArray());
    }

    /**
     * Resuelve y adjunta el equipo aplicable a cada documento de la colección.
     *
     * @param  iterable<Document>  $documents
     */
    protected function applyEmbeddedTeamToDocuments(iterable $documents, string $viewerUserId): void
    {
        foreach ($documents as $document) {
            $this->applyEmbeddedTeamToDocument($document, $viewerUserId);
        }
    }
}
