<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface ApiTeamEmbedServiceInterface
{
    /**
     * Resuelve el equipo visible para una plantilla.
     * Retorna el equipo embedido (array o null) para ser aplicado a la plantilla
     * mediante setAttribute() en el controlador.
     *
     * @return array|null El equipo embedido, listo para ser aplicado a una plantilla
     */
    public function resolveTemplateTeam(string|null $teamCatalogId, string $viewerUserId): array|null;

    /**
     * Resuelve el equipo visible para un documento.
     * Retorna el equipo embedido (array o null) para ser aplicado al documento
     * mediante setAttribute() en el controlador.
     *
     * @return array|null El equipo embedido, listo para ser aplicado a un documento
     */
    public function resolveDocumentTeam(string|null $teamCatalogId, string $viewerUserId): array|null;

    /**
     * @deprecated Use resolveTemplateTeam() instead.
     * Resuelve el equipo visible para la plantilla y lo deja listo para {@see TemplateResource}.
     *
     * @internal Provided for backward compatibility during migration.
     */
    public function embedOnTemplate($template, string $viewerUserId): void;

    /**
     * @deprecated Use resolveTemplateTeam() in a loop instead.
     * Resuelve el equipo visible para las plantillas y lo deja listo para {@see TemplateResource}.
     *
     * @internal Provided for backward compatibility during migration.
     */
    public function embedOnTemplates(iterable $templates, string $viewerUserId): void;

    /**
     * @deprecated Use resolveDocumentTeam() instead.
     * Resuelve el equipo visible para el documento y lo deja listo para {@see DocumentResource}.
     *
     * @internal Provided for backward compatibility during migration.
     */
    public function embedOnDocument($document, string $viewerUserId): void;

    /**
     * @deprecated Use resolveDocumentTeam() in a loop instead.
     * Resuelve el equipo visible para los documentos y lo deja listo para {@see DocumentResource}.
     *
     * @internal Provided for backward compatibility during migration.
     */
    public function embedOnDocuments(iterable $documents, string $viewerUserId): void;
}
