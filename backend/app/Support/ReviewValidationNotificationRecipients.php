<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Filtra destinatarios de notificación de validación según review_mode de la plantilla/documento.
 */
final class ReviewValidationNotificationRecipients
{
    /**
     * En modo secuencial solo la etapa pendiente de número más bajo recibe aviso.
     * En paralelo, todos los candidatos pendientes.
     *
     * @param  list<array{stage: int}>  $pendingWithStage
     * @return list<array{stage: int}>
     */
    public static function filterForReviewMode(string $reviewMode, array $pendingWithStage): array
    {
        if ($pendingWithStage === [] || $reviewMode !== 'sequential') {
            return $pendingWithStage;
        }

        $minStage = min(array_map(static fn (array $row): int => (int) $row['stage'], $pendingWithStage));

        return array_values(array_filter(
            $pendingWithStage,
            static fn (array $row): bool => (int) $row['stage'] === $minStage,
        ));
    }
}
