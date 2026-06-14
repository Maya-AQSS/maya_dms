<?php

declare(strict_types=1);

namespace App\DTOs\Notifications;

use App\Repositories\Contracts\DocumentRepositoryInterface;

/**
 * Revisor con un número de validaciones pendientes por encima del umbral.
 *
 * Producido por {@see DocumentRepositoryInterface::reviewersWithPendingReviewsAbove}.
 */
final readonly class PendingReviewerLoadDto
{
    public function __construct(
        public string $reviewerId,
        public int $pendingCount,
    ) {}
}
