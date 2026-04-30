<?php

namespace App\Services\Contracts;

use App\DTOs\Templates\CreateTemplateDto;
use App\DTOs\Templates\FilterTemplatesDto;
use App\DTOs\Templates\SyncUsersDto;
use App\DTOs\Templates\UpdateTemplateDto;
use App\Models\Template;
use App\Models\TemplateVersion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TemplateServiceInterface
{
    /**
     * Localiza una plantilla por su ID.
     */
    public function findOrFail(string $id): Template;

    /**
     * Carga múltiples plantillas por sus IDs aplicando el global scope de visibilidad.
     * El resultado está indexado por ID.
     *
     * @param  list<string>  $ids
     * @return \Illuminate\Database\Eloquent\Collection<string, Template>
     */
    public function findManyByIds(array $ids): \Illuminate\Database\Eloquent\Collection;

    /**
     * Resuelve la plantilla sin scope de catálogo; exige {@see \App\Policies\TemplatePolicy::view} en el controlador.
     */
    public function findOrFailWithoutCatalogScope(string $id): Template;

    /**
     * Localiza una versión de plantilla por su ID.
     */
    public function findVersionOrFail(string $versionId): TemplateVersion;

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
     * @return Collection<int, TemplateVersion>
     */
    public function listPublishedVersions(string $templateId): Collection;

    /**
     * Listado paginado con filtros (10 ítems por defecto; máximo 100 según IndexTemplateRequest).
     */
    public function paginateFiltered(FilterTemplatesDto $filters, int $perPage = 10): LengthAwarePaginator;

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
