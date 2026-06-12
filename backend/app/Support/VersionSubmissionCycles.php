<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Construye los ciclos de envío a validación que se acumulan en la columna
 * change_set de la versión de trabajo (head). Forma compartida entre
 * plantillas y documentos.
 */
final class VersionSubmissionCycles
{
    /**
     * Añade un ciclo de envío al change_set actual y devuelve la lista nueva.
     *
     * @param  mixed  $currentChangeSet  Valor actual de la columna (array o null).
     * @param  array<int, array<string, mixed>>  $blocksSnapshot
     * @return array<int, array<string, mixed>>
     */
    public static function append(mixed $currentChangeSet, string $actorId, array $blocksSnapshot): array
    {
        $cycles = is_array($currentChangeSet) ? $currentChangeSet : [];

        $cycles[] = [
            'cycle' => count($cycles) + 1,
            'submitted_at' => now()->toIso8601String(),
            'submitted_by' => $actorId,
            'blocks' => $blocksSnapshot,
        ];

        return $cycles;
    }
}
