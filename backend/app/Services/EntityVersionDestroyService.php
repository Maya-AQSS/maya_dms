<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EntityVersion;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use Illuminate\Validation\ValidationException;

/**
 * Encapsula las tres precondiciones de guardia y el reset del entity_version
 * que son idénticos en TemplateService::destroyVersion() y
 * DocumentService::destroyVersion().
 *
 * La lógica de restauración específica de dominio (bloques, revisores, sincronización
 * de estado) permanece en cada Service como wrapper fino que llama a este servicio
 * y luego ejecuta los pasos de restauración propios.
 *
 * Equivalencias de código compartido extraídas:
 *   - Guard 1: head debe ser la versión de trabajo actual (id + version_number === 0).
 *   - Guard 2: status debe ser draft|in_review|rejected.
 *   - Guard 3: debe existir una versión publicada con snapshot_data válido.
 *   - Acción: entityVersionRepository->update($head, [snapshot_data, status, changelog]).
 *
 * Los mensajes de error se parametrizan porque difieren entre dominios:
 *   "plantilla" vs "documento" (guard 1); guards 2 y 3 tienen texto idéntico.
 */
final class EntityVersionDestroyService
{
    public function __construct(
        private readonly EntityVersionRepositoryInterface $entityVersionRepository,
    ) {}

    /**
     * Valida las precondiciones de descarte y resetea el head version al
     * snapshot publicado más reciente.
     *
     * Devuelve el snapshot_data de la versión publicada para que el caller
     * pueda usarlo en la restauración específica de dominio.
     *
     * @param  class-string  $entityClass         Clase del modelo (Template::class o Document::class).
     * @param  string        $entityId            ID de la entidad.
     * @param  string        $targetVersionId     ID de versión que el cliente solicita descartar.
     * @param  EntityVersion|null $head           Versión cabecera ya cargada (headVersion).
     * @param  string        $notCurrentMessage   Mensaje cuando head no es la versión de trabajo.
     * @param  string        $statusMessage       Mensaje cuando el status no es descartable.
     * @param  string        $noPublishedMessage  Mensaje cuando no hay versión publicada.
     * @return array<string, mixed>  El snapshot_data de la versión publicada restaurada.
     *
     * @throws ValidationException si alguna precondición falla.
     */
    public function assertAndResetToPublished(
        string $entityClass,
        string $entityId,
        string $targetVersionId,
        ?EntityVersion $head,
        string $notCurrentMessage,
        string $statusMessage,
        string $noPublishedMessage,
    ): array {
        // Guard 1: el head debe ser la versión de trabajo actual (version_number === 0)
        // y su id debe coincidir con el que el cliente solicita descartar.
        if ($head === null
            || (string) $head->id !== $targetVersionId
            || (int) $head->version_number !== 0
        ) {
            throw ValidationException::withMessages([
                'version' => [$notCurrentMessage],
            ]);
        }

        // Guard 2: solo se pueden descartar versiones no publicadas.
        if (! in_array((string) $head->status, ['draft', 'in_review', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'version' => [$statusMessage],
            ]);
        }

        // Guard 3: debe existir una versión publicada con snapshot válido.
        $latestPublished = $this->entityVersionRepository->findLatestPublishedForEntity(
            $entityClass,
            $entityId,
        );

        if ($latestPublished === null || ! is_array($latestPublished->snapshot_data)) {
            throw ValidationException::withMessages([
                'version' => [$noPublishedMessage],
            ]);
        }

        $publishedSnapshot = $latestPublished->snapshot_data;

        // Acción compartida: resetear el head al snapshot publicado.
        $this->entityVersionRepository->update($head, [
            'snapshot_data' => $publishedSnapshot,
            'status' => 'published',
            'changelog' => null,
        ]);

        return $publishedSnapshot;
    }
}
