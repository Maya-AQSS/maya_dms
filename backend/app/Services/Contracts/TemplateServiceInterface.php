<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Templates\CreateTemplateDto;
use App\DTOs\Templates\FilterTemplatesDto;
use App\DTOs\Templates\SyncUsersDto;
use App\DTOs\Templates\TemplateDto;
use App\DTOs\Templates\TemplateFilterDto;
use App\DTOs\Templates\UpdateTemplateDto;
use App\DTOs\Versioning\EntityVersionDto;
use App\DTOs\Versioning\TemplateVersionDetailDto;
use App\DTOs\Versioning\TemplateVersionSummaryDto;
use App\DTOs\Versioning\WorkingRevisionConflictDto;
use App\Http\Controllers\Api\TemplateController;
use App\Models\EntityVersion;
use App\Models\Template;
use App\Policies\TemplatePolicy;
use Illuminate\Support\Collection;
use Maya\Http\Pagination\PaginatedDto;

/**
 * Los métodos de mutación devuelven {@see TemplateDto}. La presentación derivada
 * (`can_clone`, team embebido, …) que el {@see TemplateController} adjunta con
 * `setAttribute()` sobre el Model se inyecta mediante el callback opcional
 * `$beforeMap` (mismo patrón que {@see self::paginateFiltered}), aplicado justo
 * antes de la conversión a DTO.
 *
 * Excepción documentada (mantener): `findModelOrFail` / `findOrFailWithoutCatalogScope`
 * existen SOLO para `authorize($ability, $model)` con Policies (exigen Model) y para
 * el flujo de presentación de `show` sobre el modelo resuelto. Ver changes.md (F4-B1).
 *
 * Excepción R2 deliberada (decisión de arquitectura, no deuda): {@see TemplatePolicy}
 * (17 métodos) inspecciona owner_id/status/visibilidad/scopes sobre el Model ya cargado.
 * Forzar id/DTO obligaría a re-fetch dentro de cada método de Policy (N+1 en lista/bulk) o a
 * un DTO espejo del modelo. El coste de rendimiento supera al beneficio; estos métodos quedan
 * acotados a autorización (@internal authorization-only).
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
     * Alias canónico de {@see findOrFailWithoutCatalogScope} — nombre homogéneo
     * con DocumentServiceInterface::findModelOrFailWithoutUserAccess.
     */
    public function findModelOrFailWithoutUserAccess(string $id): Template;

    /**
     * Localiza una versión de plantilla por su ID.
     */
    public function findVersionOrFail(string $versionId): EntityVersionDto;

    /**
     * Localiza una versión polimórfica por su ID.
     */
    public function findEntityVersionOrFail(string $versionId): EntityVersionDto;

    /**
     * Envía el borrador a revisión. Solo el creador puede ejecutar esta acción.
     *
     * @param  callable(Template): void|null  $beforeMap
     */
    public function submitForReview(string $templateId, string $actorId, string $changelog, ?callable $beforeMap = null): TemplateDto;

    /**
     * Rechaza la revisión de la plantilla. Solo un revisor asignado puede ejecutar esta acción.
     *
     * @param  callable(Template): void|null  $beforeMap
     */
    public function rejectReview(string $templateId, string $actorId, ?callable $beforeMap = null): TemplateDto;

    /**
     * Publica la plantilla con un snapshot y emite el evento de dominio TemplatePublished.
     *
     * @param  callable(Template): void|null  $beforeMap
     */
    public function publishWithSnapshot(string $templateId, ?string $changelog, string $actorId, ?callable $beforeMap = null): TemplateDto;

    /**
     * Detalle de una versión publicada de plantilla con el snapshot de bloques
     * reconstruido y los nombres de autor/revisores ya resueltos.
     */
    public function findTemplateVersionDetailOrFail(string $versionId): TemplateVersionDetailDto;

    /**
     * Historial de versiones publicadas (metadatos, sin el JSONB de bloques) con
     * los nombres de autor/revisores ya resueltos.
     *
     * @return list<TemplateVersionSummaryDto>
     */
    public function listPublishedVersionSummaries(string $templateId): array;

    /**
     * Listado paginado de plantillas con filtros de dominio (ADR-C).
     *
     * @param  callable(Collection<int, Template>): void|null  $beforeMap
     * @return PaginatedDto<TemplateDto>
     */
    public function paginateFiltered(
        TemplateFilterDto $filter,
        string $viewerId,
        ?callable $beforeMap = null,
    ): PaginatedDto;

    /**
     * Listado con filtros visible para el usuario (sin paginación en servidor).
     *
     * @return Collection<int, TemplateDto>
     */
    public function listFiltered(FilterTemplatesDto $filters): Collection;

    /**
     * Adjunta latest_published_version_id y campos relacionados a una colección de plantillas.
     *
     * @param  Collection<int, Template>  $templates
     */
    public function attachLatestPublishedVersionMeta(Collection $templates): void;

    public function resolveWorkingRevisionConflict(Template $template): WorkingRevisionConflictDto;

    public function attachWorkingRevisionPresentationMeta(Template $template): void;

    /**
     * Para plantillas en borrador visibles al viewer que NO son su creador ni su revisor activo,
     * sobrescribe el headVersion con el último snapshot publicado (batch, sin N+1).
     *
     * @param  Collection<int, Template>  $templates
     */
    public function overlayPublishedSnapshotForNonOwners(Collection $templates, string $viewerId): void;

    /**
     * Crea una plantilla con los atributos dados.
     *
     * @param  callable(Template): void|null  $beforeMap
     */
    public function create(CreateTemplateDto $dto, ?callable $beforeMap = null): TemplateDto;

    /**
     * Actualiza una plantilla con los atributos dados.
     * Recibe el modelo ya resuelto para evitar una query redundante.
     *
     * @param  callable(Template): void|null  $beforeMap
     */
    public function update(Template $template, UpdateTemplateDto $dto, ?callable $beforeMap = null): TemplateDto;

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
     *
     * @param  callable(Template): void|null  $beforeMap
     */
    public function clone(string $sourceTemplateId, string $actorId, ?callable $beforeMap = null): TemplateDto;

    /**
     * Plantilla publicada → borrador para iniciar el ciclo de una nueva versión.
     *
     * @param  callable(Template): void|null  $beforeMap
     */
    public function startNewRevisionCycle(string $templateId, string $actorId, ?callable $beforeMap = null): TemplateDto;

    public function hasPublishedSnapshot(string $templateId): bool;

    public function findLatestPublishedVersion(string $templateId): ?EntityVersion;

    /**
     * Descarta una versión no publicada en curso y restaura la última publicación.
     *
     * @param  callable(Template): void|null  $beforeMap
     */
    public function destroyVersion(string $templateId, string $versionId, string $actorId, ?callable $beforeMap = null): TemplateDto;

    /**
     * Registra la aprobación del revisor activo. Si todos los revisores han aprobado,
     * publica la plantilla automáticamente con un snapshot.
     *
     * En modo secuencial verifica que los stages anteriores estén aprobados antes
     * de permitir que el actor apruebe.
     *
     * @param  callable(Template): void|null  $beforeMap
     */
    public function approveReview(string $templateId, string $actorId, ?callable $beforeMap = null): TemplateDto;

    /**
     * Sincroniza los revisores de la plantilla normativa.
     */
    public function syncReviewers(string $templateId, SyncUsersDto $dto): void;

    /**
     * Sincroniza el pool de posibles revisores de documentos generados desde la plantilla.
     */
    public function syncDocumentReviewers(string $templateId, SyncUsersDto $dto): void;

    /**
     * Verifica si un usuario es revisor activo para una plantilla en estado in_review.
     */
    public function isUserActiveReviewerForTemplate(string $templateId, string $userId): bool;

    /**
     * Determina si el viewer debe recibir el snapshot publicado o el contenido vivo,
     * y si es revisor asignado activo. Encapsula la lógica de branching del show()
     * del TemplateController — espejo de DocumentService::resolveDocumentViewerContext().
     *
     * @return array{serve_published_snapshot: bool, is_assigned_reviewer: bool}
     */
    public function resolveTemplateViewerContext(Template $model, string $templateId, string $viewerId): array;
}
