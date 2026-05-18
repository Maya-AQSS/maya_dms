<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\DTOs\Templates\FilterTemplatesDto;
use App\Models\Template;
use App\Policies\TemplatePolicy;
use Illuminate\Support\Collection;

interface TemplateRepositoryInterface
{
    /**
     * Localiza una plantilla por su ID o lanza una excepción.
     */
    public function findOrFail(string $id): Template;

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
     * Ejecuta una operación dentro de transacción.
     */
    public function transaction(callable $callback): mixed;
}
