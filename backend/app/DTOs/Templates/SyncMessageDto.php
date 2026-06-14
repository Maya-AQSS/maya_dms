<?php

declare(strict_types=1);

namespace App\DTOs\Templates;

use App\Http\Resources\TemplateReviewersSyncMessageResource;

/**
 * Mensaje de resultado de una sincronización de revisores de plantilla.
 *
 * Tipado del payload trivial que consume
 * {@see TemplateReviewersSyncMessageResource}, en lugar de
 * un array asociativo sin tipo.
 */
final readonly class SyncMessageDto
{
    public function __construct(
        public string $message,
    ) {}
}
