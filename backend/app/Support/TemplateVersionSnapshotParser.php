<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Extrae campos del snapshot JSONB de EntityVersion para plantillas.
 *
 * Acepta el snapshot ya decodificado como array o como string JSON; devuelve
 * null / lista vacía cuando la clave no existe o tiene el tipo incorrecto.
 * No toca la base de datos: es una clase de utilidad pura y estática.
 */
final class TemplateVersionSnapshotParser
{
    /**
     * Devuelve el ID del autor (created_by) del template dentro del snapshot.
     *
     * Busca en snapshot_data.template.created_by.
     *
     * @param  array<string, mixed>|string|null  $snapshotData
     */
    public static function authorId(mixed $snapshotData): ?string
    {
        $snapshot = self::normalize($snapshotData);
        if ($snapshot === null) {
            return null;
        }

        $authorId = data_get($snapshot, 'template.created_by');

        return is_string($authorId) && $authorId !== '' ? $authorId : null;
    }

    /**
     * Devuelve los IDs de los revisores de plantilla dentro del snapshot.
     *
     * Busca en snapshot_data.reviewers.template_reviewers[*].user_id.
     *
     * @param  array<string, mixed>|string|null  $snapshotData
     * @return list<string>
     */
    public static function reviewerIds(mixed $snapshotData): array
    {
        $snapshot = self::normalize($snapshotData);
        if ($snapshot === null) {
            return [];
        }

        $reviewers = data_get($snapshot, 'reviewers.template_reviewers');
        if (! is_array($reviewers)) {
            return [];
        }

        $ids = [];
        foreach ($reviewers as $r) {
            if (! is_array($r)) {
                continue;
            }
            $uid = $r['user_id'] ?? null;
            if (is_string($uid) && $uid !== '') {
                $ids[] = $uid;
            }
        }

        return $ids;
    }

    /**
     * Normaliza el snapshot a array o null si no es válido.
     *
     * @param  array<string, mixed>|string|null  $snapshotData
     * @return array<string, mixed>|null
     */
    private static function normalize(mixed $snapshotData): ?array
    {
        if (is_array($snapshotData)) {
            return $snapshotData;
        }

        if (is_string($snapshotData) && $snapshotData !== '') {
            $decoded = json_decode($snapshotData, true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }
}
