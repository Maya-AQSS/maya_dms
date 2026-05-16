<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Http\Resources\DocumentResource;
use App\Http\Resources\TemplateResource;
use App\Models\Document;
use App\Models\Template;

interface ApiTeamEmbedServiceInterface
{
    /**
     * Resuelve el equipo visible para la plantilla y lo deja listo para {@see TemplateResource}.
     */
    public function embedOnTemplate(Template $template, string $viewerUserId): void;

    /**
     * Resuelve el equipo visible para las plantillas y lo deja listo para {@see TemplateResource}.
     */
    public function embedOnTemplates(iterable $templates, string $viewerUserId): void;

    /**
     * Resuelve el equipo según la plantilla del documento y lo deja listo para {@see DocumentResource}.
     */
    public function embedOnDocument(Document $document, string $viewerUserId): void;

    /**
     * Resuelve el equipo visible para los documentos y lo deja listo para {@see DocumentResource}.
     */
    public function embedOnDocuments(iterable $documents, string $viewerUserId): void;
}
