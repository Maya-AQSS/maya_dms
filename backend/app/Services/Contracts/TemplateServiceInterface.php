<?php

namespace App\Services\Contracts;

use App\DTOs\Templates\CreateTemplateDto;
use App\DTOs\Templates\FilterTemplatesDto;
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
     * Resuelve la plantilla sin scope de catálogo; exige {@see \App\Policies\TemplatePolicy::view} en el controlador.
     */
    public function findOrFailWithoutCatalogScope(string $id): Template;

    /**
     * Localiza una versión de plantilla por su ID.
     */
    public function findVersionOrFail(string $versionId): TemplateVersion;

    /**
     * Transiciona la plantilla a un nuevo estado y emite el evento de dominio TemplateStateChanged.
     */
    public function transition(string $templateId, string $newStatus, string $actorId): Template;

    /**
     * Envia el borrador a revisión (autor o quien puede editar la plantilla).
     */
    public function submitForReview(string $templateId, string $actorId): Template;

    /**
     * Rechaza la revisión de la plantilla (autor o quien puede editar la plantilla).
     */
    public function rejectReview(string $templateId, string $actorId): Template;

    /**
     * Publica la plantilla con un snapshot y emite el evento de dominio TemplatePublished.
     */
    public function publishWithSnapshot(string $templateId, ?string $changelog, string $actorId): Template;

    /**
     * @return Collection<int, TemplateVersion>
     */
    public function listPublishedVersions(string $templateId): Collection;

    /**
     * Listado paginado con filtros (20 ítems por defecto en request).
     */
    public function paginateFiltered(FilterTemplatesDto $filters, int $perPage = 20): LengthAwarePaginator;

    /**
     * Crea una plantilla con los atributos dados.
     */
    public function create(CreateTemplateDto $dto): Template;

    /**
     * Actualiza una plantilla con los atributos dados.
     */
    public function update(string $templateId, UpdateTemplateDto $dto): Template;

    /**
     * Elimina una plantilla físicamente (no se archiva).
     *
     * @return bool true si se eliminó físicamente; false si solo se archivó (hay documentos asociados).
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
     * @param array<int, string> $userIds Lista ordenada de IDs de usuario.
     */
    public function syncReviewers(string $templateId, array $userIds): void;

    /**
     * Sincroniza el pool de posibles revisores de documentos generados desde la plantilla.
     * @param array<int, string> $userIds IDs de usuario elegibles como revisores de documentos.
     */
    public function syncDocumentReviewers(string $templateId, array $userIds): void;
}
