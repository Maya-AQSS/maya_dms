<?php

namespace App\Repositories\Contracts;

use App\DTOs\Templates\FilterTemplatesDto;
use App\Models\Template;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TemplateRepositoryInterface
{
    /**
     * Localiza una plantilla por su ID o lanza una excepción.
     */
    public function findOrFail(string $id): Template;

    /**
     * Indica si el usuario es creador o revisor asignado de la plantilla.
     * Usado para control de acceso al historial de auditoría.
     */
    public function isCreatorOrReviewer(string $templateId, string $userId): bool;

    /**
     * Listado paginado con filtros (sin cargar bloques).
     */
    public function paginateFiltered(FilterTemplatesDto $filters, int $perPage = 20): LengthAwarePaginator;

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
}
