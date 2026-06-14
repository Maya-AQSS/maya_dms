<?php

declare(strict_types=1);

namespace App\DTOs\Notifications;

use App\Repositories\Contracts\DocumentRepositoryInterface;

/**
 * Documento cuya fecha de validación vence próximamente y aún no está publicado.
 *
 * Producido por {@see DocumentRepositoryInterface::findApproachingDeadline}
 * para que la regla de notificación solo orqueste y publique.
 */
final readonly class ApproachingDeadlineDocumentDto
{
    public function __construct(
        public string $documentId,
        public string $title,
        public string $ownerId,
        public string $deadline,
    ) {}
}
