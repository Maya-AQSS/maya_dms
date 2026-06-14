<?php

declare(strict_types=1);

namespace App\DTOs\Versioning;

use App\Http\Resources\TemplateVersionResource;
use App\Services\TemplateService;

/**
 * Detalle de una versión publicada de plantilla, con el snapshot de bloques ya
 * reconstruido y los nombres de autor/revisores resueltos.
 *
 * Devuelto por {@see TemplateService::findTemplateVersionDetailOrFail()}.
 * El Resource ({@see TemplateVersionResource}) solo mapea
 * estos campos a la respuesta JSON sin tocar la base de datos.
 */
final readonly class TemplateVersionDetailDto
{
    /**
     * @param  array<string, mixed>|null  $templateSnapshot
     * @param  array<int, array<string, mixed>>  $blocksSnapshot
     * @param  list<string>  $reviewerNames
     */
    public function __construct(
        public string $id,
        public string $templateId,
        public int $versionNumber,
        public ?array $templateSnapshot,
        public array $blocksSnapshot,
        public ?string $changelog,
        public ?string $publishedBy,
        public ?string $publishedByName,
        public ?string $authorName,
        public array $reviewerNames,
        public ?string $publishedAt,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}
}
