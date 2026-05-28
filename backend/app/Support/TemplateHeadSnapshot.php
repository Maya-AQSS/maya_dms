<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\TemplateVisibilityLevel;
use App\Models\EntityVersion;
use App\Models\Template;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Forma canónica de la clave `template` dentro de {@see EntityVersion::snapshot_data}
 * para la versión cabezal (número 0) de una plantilla.
 */
final class TemplateHeadSnapshot
{
    public const JSON_TEMPLATE_KEY = 'template';

    /**
     * Atributos de negocio servidos desde el snapshot (no existen en fila `templates` tras el refactor).
     *
     * @var list<string>
     */
    public const DELEGATED_ATTRIBUTES = [
        'name',
        'description',
        'visibility_level',
        'delivery_deadline',
        'study_type_id',
        'study_id',
        'module_id',
        'team_id',
        'created_by',
        'status',
        'review_stages',
        'review_mode',
    ];

    /**
     * @param  array<string, mixed>  $row  Fila de `templates` (objeto o array) con columnas legacy.
     * @return array{template: array<string, mixed>}
     */
    public static function buildPayloadFromLegacyRow(object|array $row, string $templateId, string $processId): array
    {
        $r = (array) $row;
        $visibility = $r['visibility_level'] ?? TemplateVisibilityLevel::Personal->value;
        if ($visibility instanceof TemplateVisibilityLevel) {
            $visibility = $visibility->value;
        }
        $deadline = $r['delivery_deadline'] ?? null;
        if ($deadline instanceof \DateTimeInterface) {
            $deadline = $deadline->format('Y-m-d H:i:s');
        } elseif (is_string($deadline)) {
            $deadline = $deadline;
        } else {
            $deadline = null;
        }

        return [
            self::JSON_TEMPLATE_KEY => [
                'id' => $templateId,
                'process_id' => $processId,
                'name' => (string) ($r['name'] ?? ''),
                'description' => $r['description'] ?? null,
                'visibility_level' => (string) $visibility,
                'delivery_deadline' => $deadline,
                'study_type_id' => $r['study_type_id'] ?? null,
                'study_id' => $r['study_id'] ?? null,
                'module_id' => $r['module_id'] ?? null,
                'team_id' => $r['team_id'] ?? null,
                'created_by' => (string) ($r['created_by'] ?? ''),
                'status' => (string) ($r['status'] ?? 'draft'),
                'review_stages' => (int) ($r['review_stages'] ?? 0),
                'review_mode' => (string) ($r['review_mode'] ?? 'parallel'),
            ],
        ];
    }

    /**
     * Expresión SQL para leer un campo bajo `snapshot_data.template` (compat. sqlite / mysql).
     */
    public static function jsonTemplateFieldExpression(string $entityVersionsAlias, string $field): string
    {
        $alias = rtrim($entityVersionsAlias, '.');
        $path = '$.template.'.str_replace("'", "''", $field);
        $driver = DB::getDriverName();

        $fieldSql = preg_replace('/[^a-zA-Z0-9_]/', '', $field) ?? '';

        return match ($driver) {
            'mysql' => "JSON_UNQUOTE(JSON_EXTRACT({$alias}.snapshot_data, '{$path}'))",
            'pgsql' => "({$alias}.snapshot_data->'template'->>'{$fieldSql}')",
            default => "json_extract({$alias}.snapshot_data, '{$path}')",
        };
    }

    /**
     * Lectura JSON comparable con columnas tipo `VARCHAR` (todas las
     * referencias a entidades del ecosistema Maya son varchar tras
     * unificar con `maya/shared-profile-laravel`). El operador `->>`
     * devuelve `text`, compatible directamente con varchar.
     */
    public static function jsonTemplateFieldUuidExpression(string $entityVersionsAlias, string $field): string
    {
        return self::jsonTemplateFieldExpression($entityVersionsAlias, $field);
    }

    /**
     * @param  array<string, mixed>  $snapshotData
     * @return array<string, mixed>
     */
    public static function mergeTemplateKey(array $snapshotData, array $updates): array
    {
        $template = $snapshotData[self::JSON_TEMPLATE_KEY] ?? [];
        if (! is_array($template)) {
            $template = [];
        }
        foreach ($updates as $k => $v) {
            if ($v === null && ! in_array($k, ['description', 'delivery_deadline', 'study_type_id', 'study_id', 'module_id', 'team_id'], true)) {
                continue;
            }
            $template[$k] = $v;
        }
        $snapshotData[self::JSON_TEMPLATE_KEY] = $template;

        return $snapshotData;
    }

    public static function normalizeVisibilityForSnapshot(mixed $level): string
    {
        if ($level instanceof TemplateVisibilityLevel) {
            return $level->value;
        }

        return (string) $level;
    }

    public static function normalizeDeadlineForSnapshot(mixed $deadline): ?string
    {
        if ($deadline === null) {
            return null;
        }
        if ($deadline instanceof Carbon) {
            return $deadline->format('Y-m-d H:i:s');
        }
        if ($deadline instanceof \DateTimeInterface) {
            return Date::parse($deadline)->format('Y-m-d H:i:s');
        }

        return Date::parse((string) $deadline)->format('Y-m-d H:i:s');
    }
}
