<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Templates\CreateTemplateDto;
use App\DTOs\Templates\FilterTemplatesDto;
use App\DTOs\Templates\SyncUsersDto;
use App\DTOs\Templates\TemplateDto;
use App\DTOs\Templates\UpdateTemplateDto;
use App\Http\Controllers\Api\TemplateController;
use App\Models\EntityVersion;
use App\Models\Template;
use App\Policies\TemplatePolicy;
use Illuminate\Support\Collection;

/**
 * Excepción B4 documentada: análoga a {@see DocumentServiceInterface} —
 * la mayoría de métodos de mutación devuelven el Model Eloquent porque el
 * {@see TemplateController} adjunta atributos
 * derivados (`can_clone`, `review_mode`, etc.) mediante `setAttribute()` antes
 * de presentar como DTO. La conversión final a DTO se hace en el Controller
 * con `TemplateDto::fromModel($model)` antes de pasar al Resource (que es
 * `TemplateDto`-only estricto).
 */
interface TemplateServiceInterface
{
    /**
     * Canónico: devuelve el DTO de la plantilla. Lanza ModelNotFoundException
     * si no existe.
     */
    public function findOrFail(string $id): TemplateDto;

    /**
     * Variante de uso interno: devuelve el Model. Necesario cuando el caller
     * adjunta atributos derivados con `setAttribute()`, invoca
     * `authorize($ability, $model)`, o encadena a `update($model, ...)` de
     * este mismo Service.
     */
    public function findModelOrFail(string $id): Template;

    /**
     * Carga múltiples plantillas por sus IDs aplicando el global scope de visibilidad.
     * El resultado está indexado por ID.
     *
     * @param  list<string>  $ids
     * @return \Illuminate\Database\Eloquent\Collection<string, Template>
     */
    public function findManyByIds(array $ids): \Illuminate\Database\Eloquent\Collection;

    /**
     * Resuelve la plantilla sin scope de catálogo; exige {@see TemplatePolicy::view} en el controlador.
     */
    public function findOrFailWithoutCatalogScope(string $id): Template;

    /**
     * Localiza una versión de plantilla por su ID.
     */
    public function findVersionOrFail(string $versionId): EntityVersion;

    /**
     * Localiza una versión polimórfica por su ID.
     */
    public function findEntityVersionOrFail(string $versionId): EntityVersion;

    /**
     * Envía el borrador a revisión. Solo el creador puede ejecutar esta acción.
     */
    public function submitForReview(string $templateId, string $actorId): Template;

    /**
     * Rechaza la revisión de la plantilla. Solo un revisor asignado puede ejecutar esta acción.
     */
    public function rejectReview(string $templateId, string $actorId): Template;

    /**
     * Publica la plantilla con un snapshot y emite el evento de dominio TemplatePublished.
     */
    public function publishWithSnapshot(string $templateId, ?string $changelog, string $actorId): Template;

    /**
     * Lista todas las versiones publicadas de una plantilla ordenadas por número de versión.
     *
     * @return Collection<int, EntityVersion>
     */
    public function listPublishedVersions(string $templateId): Collection;

    /**
     * Listado con filtros visible para el usuario (sin paginación en servidor).
     *
     * @return Collection<int, Template>
     */
    public function listFiltered(FilterTemplatesDto $filters): Collection;

    /**
     * Adjunta latest_published_version_id y campos relacionados a una colección de plantillas.
     *
     * @param  Collection<int, Template>  $templates
     */
    public function attachLatestPublishedVersionMeta(Collection $templates): void;

    /**
     * Para plantillas en borrador visibles al viewer que NO son su creador ni su revisor activo,
     * sobrescribe el headVersion con el último snapshot publicado (batch, sin N+1).
     *
     * @param  Collection<int, Template>  $templates
     */
    public function overlayPublishedSnapshotForNonOwners(Collection $templates, string $viewerId): void;

    /**
     * Crea una plantilla con los atributos dados.
     */
    public function create(CreateTemplateDto $dto): Template;

    /**
     * Actualiza una plantilla con los atributos dados.
     * Recibe el modelo ya resuelto para evitar una query redundante.
     */
    public function update(Template $template, UpdateTemplateDto $dto): Template;

    /**
     * Elimina una plantilla de forma recuperable.
     *
     * - Con documentos: transiciona a `archived` (recuperable cambiando estado).
     * - Sin documentos: soft delete con deleted_at (recuperable con restore).
     *
     * @return bool true si se eliminó por soft delete (sin documentos); false si se archivó (con documentos).
     */
    public function destroy(string $templateId, string $actorId): bool;

    /**
     * Clona una plantilla origen hacia una nueva destino.
     */
    public function clone(string $sourceTemplateId, string $actorId): Template;

    /**
     * Plantilla publicada → borrador para iniciar el ciclo de una nueva versión.
     */
    public function startNewRevisionCycle(string $templateId, string $actorId): Template;

    public function hasPublishedSnapshot(string $templateId): bool;

    public function findLatestPublishedVersion(string $templateId): ?EntityVersion;

    /**
     * Descarta una versión no publicada en curso y restaura la última publicación.
     */
    public function destroyVersion(string $templateId, string $versionId, string $actorId): Template;

    /**
     * Registra la aprobación del revisor activo. Si todos los revisores han aprobado,
     * publica la plantilla automáticamente con un snapshot.
     *
     * En modo secuencial verifica que los stages anteriores estén aprobados antes
     * de permitir que el actor apruebe.
     */
    public function approveReview(string $templateId, string $actorId): Template;

    /**
     * Sincroniza los revisores de la plantilla normativa.
     */
    public function syncReviewers(string $templateId, SyncUsersDto $dto): void;

    /**
     * Sincroniza el pool de posibles revisores de documentos generados desde la plantilla.
     */
    public function syncDocumentReviewers(string $templateId, SyncUsersDto $dto): void;
}
