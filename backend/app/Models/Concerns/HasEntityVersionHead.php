<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Crea la fila cabezal en `entity_versions` (version_number=0) justo antes de persistir el modelo.
 *
 * Las clases que usan este trait deben implementar tres métodos estáticos:
 *  - {@see static::delegatedAttributes()} — lista de atributos delegados al snapshot.
 *  - {@see static::buildHeadSnapshot()} — construye el payload JSON del snapshot.
 *  - {@see static::snapshotCreatedByKey()} — clave dentro del snapshot donde vive `created_by`.
 *
 * El hook se registra vía {@see static::bootHasEntityVersionHead()} que Laravel invoca
 * automáticamente al inicializar el modelo, antes que {@see static::booted()}. El método
 * protegido {@see static::registerEntityVersionHeadHook()} es llamado desde {@see static::booted()}
 * en cada clase concreta para preservar el orden de boot respecto a los global scopes.
 */
trait HasEntityVersionHead
{
    /**
     * Lista de atributos que viven en el snapshot y no en la fila del modelo.
     *
     * @return list<string>
     */
    abstract protected static function delegatedAttributes(): array;

    /**
     * Construye el array de payload que se almacenará en `snapshot_data`.
     *
     * @param  array<string, mixed>  $row       Atributos del modelo antes de persistir.
     * @param  string                $modelId   UUID del modelo.
     * @param  string                $processId UUID del proceso.
     * @return array<string, mixed>
     */
    abstract protected static function buildHeadSnapshot(array $row, string $modelId, string $processId): array;

    /**
     * Clave de acceso dentro del snapshot para el campo `created_by`.
     * Por ejemplo: `['document', 'created_by']` o `['template', 'created_by']`.
     *
     * @return list<string>
     */
    abstract protected static function snapshotCreatedByKey(): array;

    /**
     * Validaciones adicionales antes de construir el snapshot (lanza excepción si falla).
     * Por defecto no hace nada; Document sobreescribe para comprobar template_id.
     *
     * @param  static  $model
     */
    protected static function validateBeforeHeadSnapshot(self $model): void
    {
        // No-op por defecto.
    }

    /**
     * Registra el hook `creating` que inserta la fila cabezal en entity_versions.
     * Debe llamarse desde {@see static::booted()} en cada clase concreta, DESPUÉS
     * de añadir los global scopes, para que el orden relativo no cambie.
     */
    protected static function registerEntityVersionHeadHook(): void
    {
        static::creating(function (self $model): void {
            $delegated = static::delegatedAttributes();

            // Si ya tiene un head asignado externamente (p.ej. clonar), solo
            // limpia los atributos delegados que no deben persistir en la fila.
            if ($model->head_entity_version_id !== null) {
                foreach ($delegated as $attr) {
                    if (array_key_exists($attr, $model->getAttributes())) {
                        $model->offsetUnset($attr);
                    }
                }

                return;
            }

            // Comprobamos si hay algún atributo delegado presente.
            $attrs = $model->getAttributes();
            $hasDelegated = false;
            foreach ($delegated as $attr) {
                if (array_key_exists($attr, $attrs)) {
                    $hasDelegated = true;
                    break;
                }
            }

            if (! $hasDelegated) {
                return;
            }

            // Garantizar process_id.
            if (empty($model->process_id)) {
                $model->process_id = \App\Models\Process::query()->value('id') ?? '00000000-0000-0000-0000-000000000001';
            }

            // Validaciones específicas de la clase (p.ej. template_id obligatorio en Document).
            static::validateBeforeHeadSnapshot($model);

            $row = $model->getAttributes();
            $modelId = (string) $model->getKey();
            $processId = (string) $model->process_id;
            $snapshot = static::buildHeadSnapshot($row, $modelId, $processId);

            $headId = (string) Str::uuid();
            $now = now();
            $status = (string) ($row['status'] ?? 'draft');
            if ($status === 'published') {
                $status = 'draft';
            }

            $snapshotCreatedBy = data_get($snapshot, implode('.', static::snapshotCreatedByKey()), '');
            $createdBy = (string) ($row['created_by'] ?? $snapshotCreatedBy ?? '');

            DB::table('entity_versions')->insert([
                'id' => $headId,
                'versionable_type' => static::class,
                'versionable_id' => $modelId,
                'version_number' => 0,
                'base_version_id' => null,
                'change_set' => null,
                'status' => $status,
                'created_by' => $createdBy,
                'published_by' => null,
                'published_at' => null,
                'changelog' => null,
                'snapshot_data' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'is_snapshot_immutable' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $model->setAttribute('head_entity_version_id', $headId);

            foreach ($delegated as $attr) {
                if (array_key_exists($attr, $model->getAttributes())) {
                    $model->offsetUnset($attr);
                }
            }
        });
    }
}
