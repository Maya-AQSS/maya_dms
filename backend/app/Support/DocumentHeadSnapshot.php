<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Forma canónica de la clave `document` dentro de {@see \App\Models\EntityVersion::snapshot_data}
 * para la versión cabezal (número 0) de un documento.
 */
final class DocumentHeadSnapshot
{
    public const JSON_DOCUMENT_KEY = 'document';

    /**
     * Atributos servidos desde el snapshot (no existen en fila `documents` tras el refactor).
     *
     * @var list<string>
     */
    public const DELEGATED_ATTRIBUTES = [
        'title',
        'study_type_id',
        'study_id',
        'module_id',
        'team_id',
        'delivery_deadline',
        'created_by',
        'owner_id',
        'status',
    ];

    /**
     * @param  array<string, mixed>  $row  Fila de `documents` (objeto o array) con columnas legacy.
     * @return array{document: array<string, mixed>}
     */
    public static function buildPayloadFromLegacyRow(
        object|array $row,
        string $documentId,
        string $processId,
        string $templateId,
    ): array {
        $r = (array) $row;
        $deadline = $r['delivery_deadline'] ?? null;
        if ($deadline instanceof \DateTimeInterface) {
            $deadline = $deadline->format('Y-m-d H:i:s');
        } elseif (is_string($deadline)) {
            $deadline = $deadline;
        } else {
            $deadline = null;
        }

        return [
            self::JSON_DOCUMENT_KEY => [
                'id' => $documentId,
                'process_id' => $processId,
                'template_id' => $templateId,
                'title' => (string) ($r['title'] ?? ''),
                'study_type_id' => $r['study_type_id'] ?? null,
                'study_id' => $r['study_id'] ?? null,
                'module_id' => $r['module_id'] ?? null,
                'team_id' => $r['team_id'] ?? null,
                'delivery_deadline' => $deadline,
                'created_by' => (string) ($r['created_by'] ?? ''),
                'owner_id' => (string) ($r['owner_id'] ?? ''),
                'status' => (string) ($r['status'] ?? 'draft'),
            ],
        ];
    }

    /**
     * Expresión SQL para leer un campo bajo `snapshot_data.document` (compat. sqlite / mysql).
     */
    public static function jsonDocumentFieldExpression(string $entityVersionsAlias, string $field): string
    {
        $alias = rtrim($entityVersionsAlias, '.');
        $path = '$.document.'.str_replace("'", "''", $field);
        $driver = DB::getDriverName();

        $fieldSql = preg_replace('/[^a-zA-Z0-9_]/', '', $field) ?? '';

        return match ($driver) {
            'mysql' => "JSON_UNQUOTE(JSON_EXTRACT({$alias}.snapshot_data, '{$path}'))",
            'pgsql' => "({$alias}.snapshot_data->'document'->>'{$fieldSql}')",
            default => "json_extract({$alias}.snapshot_data, '{$path}')",
        };
    }

    /**
     * @param  array<string, mixed>  $snapshotData
     * @return array<string, mixed>
     */
    public static function mergeDocumentKey(array $snapshotData, array $updates): array
    {
        $document = $snapshotData[self::JSON_DOCUMENT_KEY] ?? [];
        if (! is_array($document)) {
            $document = [];
        }
        foreach ($updates as $k => $v) {
            if ($v === null && ! in_array($k, ['study_type_id', 'study_id', 'module_id', 'team_id', 'delivery_deadline'], true)) {
                continue;
            }
            $document[$k] = $v;
        }
        $snapshotData[self::JSON_DOCUMENT_KEY] = $document;

        return $snapshotData;
    }
}
