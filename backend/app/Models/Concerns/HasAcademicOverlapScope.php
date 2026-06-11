<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Añade el método {@see static::applyAcademicOverlapForTableAlias()} que emite tres
 * OR-EXISTS sobre `user_study_types`, `user_studies` y `user_course_modules`,
 * correlacionados con el snapshot cabezal (version_number = 0) de la entidad.
 *
 * El único punto de divergencia entre Document y Template es la expresión SQL que
 * lee un campo del snapshot JSON. Las clases concretas implementan
 * {@see static::headSnapshotJsonFieldExpression()} devolviendo dicha expresión.
 */
trait HasAcademicOverlapScope
{
    /**
     * Expresión SQL para leer un campo de `snapshot_data` del cabezal (version_number=0).
     *
     * El alias de tabla recibido es el de la fila de `entity_versions` dentro de la
     * subconsulta; el campo es el nombre de la clave JSON (p.ej. `study_type_id`).
     *
     * Ejemplo de implementación en Document:
     *   return DocumentHeadSnapshot::jsonDocumentFieldExpression($alias, $field);
     *
     * Ejemplo en Template:
     *   return TemplateHeadSnapshot::jsonTemplateFieldExpression($alias, $field);
     */
    abstract protected static function headSnapshotJsonFieldExpression(string $alias, string $field): string;

    /**
     * Aplica tres OR-EXISTS de solapamiento académico usando el alias de tabla indicado.
     *
     * El SQL emitido es equivalente a la lógica original de cada modelo:
     *   - EXISTS user_study_types JOIN snapshot head (study_type_id)
     *   - OR EXISTS user_studies JOIN snapshot head (study_id)
     *   - OR EXISTS user_course_modules JOIN snapshot head (module_id)
     *
     * @param  Builder|QueryBuilder  $query
     * @param  string                $userId  UUID del usuario autenticado.
     * @param  string                $alias   Alias de la tabla del modelo (p.ej. `documents`, `templates`, `d`).
     */
    public static function applyAcademicOverlapForTableAlias(Builder|QueryBuilder $query, string $userId, string $alias): void
    {
        $t = rtrim($alias, '.');
        $query->where(function ($w) use ($userId, $t) {
            $w->whereExists(function ($sub) use ($userId, $t) {
                $sub->select(DB::raw(1))
                    ->from('entity_versions')
                    ->whereColumn('entity_versions.versionable_id', $t.'.id')
                    ->where('entity_versions.versionable_type', static::class)
                    ->where('entity_versions.version_number', 0)
                    ->whereExists(function ($inner) use ($userId) {
                        $inner->select(DB::raw(1))
                            ->from('user_study_types')
                            ->where('user_study_types.user_id', $userId)
                            ->whereRaw(
                                'user_study_types.study_type_id = '.static::headSnapshotJsonFieldExpression('entity_versions', 'study_type_id')
                            );
                    });
            })->orWhereExists(function ($sub) use ($userId, $t) {
                $sub->select(DB::raw(1))
                    ->from('entity_versions')
                    ->whereColumn('entity_versions.versionable_id', $t.'.id')
                    ->where('entity_versions.versionable_type', static::class)
                    ->where('entity_versions.version_number', 0)
                    ->whereExists(function ($inner) use ($userId) {
                        $inner->select(DB::raw(1))
                            ->from('user_studies')
                            ->where('user_studies.user_id', $userId)
                            ->whereRaw(
                                'user_studies.study_id = '.static::headSnapshotJsonFieldExpression('entity_versions', 'study_id')
                            );
                    });
            })->orWhereExists(function ($sub) use ($userId, $t) {
                $sub->select(DB::raw(1))
                    ->from('entity_versions')
                    ->whereColumn('entity_versions.versionable_id', $t.'.id')
                    ->where('entity_versions.versionable_type', static::class)
                    ->where('entity_versions.version_number', 0)
                    ->whereExists(function ($inner) use ($userId) {
                        $inner->select(DB::raw(1))
                            ->from('user_course_modules')
                            ->where('user_course_modules.user_id', $userId)
                            ->whereRaw(
                                'user_course_modules.module_id = '.static::headSnapshotJsonFieldExpression('entity_versions', 'module_id')
                            );
                    });
            });
        });
    }
}
