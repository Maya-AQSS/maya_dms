<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\DTOs\Templates\FilterTemplatesDto;
use App\DTOs\Templates\TemplateFilterDto;
use App\DTOs\Templates\TemplateRenderDto;
use App\Models\Template;
use App\Policies\TemplatePolicy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TemplateRepositoryInterface
{
    /**
     * Localiza una plantilla por su ID o lanza una excepción.
     */
    public function findOrFail(string $id): Template;

    /**
     * Localiza una plantilla por el ID de una de sus entity_versions o lanza excepción.
     * Sin scope de catálogo; usado para autorización vía Gate en flujo de favoritos.
     */
    public function findOrFailByVersionId(string $entityVersionId): Template;

    /**
     * Localiza una plantilla por su ID con lock FOR UPDATE o lanza excepción.
     */
    public function findOrFailForUpdate(string $id): Template;

    /**
     * Igual que {@see self::findOrFail} pero sin el global scope de catálogo `user_access`.
     * Solo para rutas que aplican {@see TemplatePolicy::view} después.
     */
    public function findOrFailWithoutCatalogScope(string $id): Template;

    /**
     * Plantilla sin scope de catálogo con bloques cargados y ordenados por sort_order.
     * Para definición de bloques de documento cuando no hay snapshot de versión usable.
     */
    public function findOrFailWithBlocksOrderedWithoutCatalogScope(string $id): Template;

    /**
     * Listado paginado de plantillas con filtros de dominio (ADR-C).
     *
     * Aplica el scope global `user_access` del modelo para garantizar visibilidad.
     *
     * @return LengthAwarePaginator<Template>
     */
    public function paginateFiltered(TemplateFilterDto $filter): LengthAwarePaginator;

    /**
     * Listado con filtros (sin cargar bloques); sin paginación en servidor.
     *
     * @return Collection<int, Template>
     */
    public function listFiltered(FilterTemplatesDto $filters): Collection;

    /**
     * Rellena en memoria `latest_published_*` desde `entity_versions` (última versión publicada por plantilla).
     *
     * @param  Collection<int, Template>  $templates
     */
    public function attachLatestPublishedVersionMeta(Collection $templates): void;

    /**
     * Crea una plantilla con los atributos dados.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Template;

    /**
     * Actualiza una plantilla con los atributos dados.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Template $template, array $attributes): Template;

    /**
     * Indica si existe algún documento (incl. borrados en soft delete) asociado a la plantilla.
     * Impide forceDelete por FK restrict.
     */
    public function templateHasDocuments(string $templateId): bool;

    /**
     * Replica los bloques de una plantilla origen hacia otra destino.
     */
    public function replicateBlocks(Template $source, Template $target): void;

    /**
     * Inserta bloques en una plantilla desde el JSON de un snapshot publicado (ids de origen ignorados).
     *
     * @param  array<int, array<string, mixed>>  $blocksSnapshot
     */
    public function insertBlocksFromPublishedSnapshot(string $templateId, array $blocksSnapshot): void;

    /**
     * Carga múltiples plantillas por sus IDs (con el global scope activo).
     * El resultado está indexado por ID (keyBy).
     *
     * @param  list<string>  $ids
     * @return \Illuminate\Database\Eloquent\Collection<string, Template>
     */
    public function findManyByIds(array $ids): \Illuminate\Database\Eloquent\Collection;

    /**
     * Lista plantillas publicadas disponibles para un módulo.
     *
     * @return Collection<int, Template>
     */
    public function listPublishedByModule(string $moduleId): Collection;

    /**
     * Recupera plantilla para resolver candidatos de revisión documental sin scope de catálogo.
     * Debe incluir relaciones de reviewers y documentReviewers ordenadas.
     */
    public function findForDocumentReviewCandidatesWithoutCatalogScope(string $templateId): ?Template;

    /**
     * Bandeja de revisión de plantillas pendientes para un revisor.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function listPendingReviewInboxForUser(string $userId): Collection;

    /**
     * Etapa mínima entre revisores de plantilla con status pending, o null si no hay pendientes.
     */
    public function minPendingReviewStageForTemplate(string $templateId): ?int;

    /**
     * Ejecuta una operación dentro de transacción.
     */
    public function transaction(callable $callback): mixed;

    /**
     * Obtiene el nombre de un usuario por su ID (para UI, p. ej. mostrar quién edita).
     */
    public function getUserNameById(string $userId): ?string;

    /**
     * Verifica si una plantilla tiene revisores asignados.
     */
    public function templateHasReviewers(string $templateId): bool;

    /**
     * Sincroniza revisores de plantilla via relación (forceDelete old, create new).
     *
     * @param  array<int, array{user_id: string, stage: int}>  $reviewerData
     */
    public function syncTemplateReviewers(string $templateId, array $reviewerData): void;

    /**
     * Sincroniza revisores de documentos via relación (delete old, create new).
     *
     * @param  array<int, array{user_id: string, stage: int}>  $reviewerData
     */
    public function syncDocumentReviewers(string $templateId, array $reviewerData): void;

    /**
     * Verifica si una plantilla tiene al menos un revisor asignado.
     */
    public function doesntHaveReviewers(string $templateId): bool;

    /**
     * Verifica si una plantilla tiene al menos un validador de documento asignado.
     */
    public function doesntHaveDocumentReviewers(string $templateId): bool;

    /**
     * Actualiza el estado de todos los revisores de una plantilla.
     */
    public function updateReviewersStatus(string $templateId, string $status): void;

    /**
     * Actualiza el snapshot de la versión cabezal (headVersion) con datos específicos.
     */
    public function updateHeadVersionSnapshot(string $templateId, array $snapshotData): void;

    /**
     * Limpia datos de submission del head version snapshot (cuando se publica).
     */
    public function cleanHeadVersionSubmissionData(string $templateId): void;

    /**
     * Persiste el changelog de envío a validación en la versión de trabajo (head).
     */
    public function updateHeadVersionChangelog(string $templateId, string $changelog): void;

    /**
     * Elimina el changelog de envío de la versión de trabajo (head).
     */
    public function clearHeadVersionChangelog(string $templateId): void;

    /**
     * Fetch template data for rendering (HTML export/preview).
     * Returns template ID, name, description, theme_id, and blocks ordered by sort_order.
     * Blocks contain: id, title, default_content.
     * Without global catalog scope; caller must authorize.
     */
    public function findForRenderingWithoutCatalogScope(string $id): ?TemplateRenderDto;
}
