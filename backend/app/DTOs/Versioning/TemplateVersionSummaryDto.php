<?php

declare(strict_types=1);

namespace App\DTOs\Versioning;

use App\Http\Resources\TemplateVersionSummaryResource;
use App\Services\TemplateService;

/**
 * Metadatos de una versión publicada de plantilla para el listado de historial
 * (sin el JSONB de bloques), con los nombres de autor/revisores ya resueltos.
 *
 * Devuelto por {@see TemplateService::listPublishedVersionSummaries()}.
 * El Resource ({@see TemplateVersionSummaryResource}) solo
 * mapea estos campos a la respuesta JSON sin tocar la base de datos.
 */
final readonly class TemplateVersionSummaryDto
{
    /**
     * @param  list<string>  $reviewerNames
     */
    public function __construct(
        public string $id,
        public string $templateId,
        public int $versionNumber,
        public ?string $publishedAt,
        public ?string $publishedBy,
        public ?string $publishedByName,
        public ?string $authorName,
        public array $reviewerNames,
        public ?string $changelog,
    ) {}
}
