<?php

namespace App\Services\Contracts;

use App\DTOs\Templates\CreateTemplateDto;
use App\DTOs\Templates\FilterTemplatesDto;
use App\DTOs\Templates\UpdateTemplateDto;
use App\Models\Template;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TemplateServiceInterface
{
    /**
     * Localiza una plantilla por su ID.
     */
    public function findOrFail(string $id): Template;

    /**
     * Transiciona la plantilla a un nuevo estado y emite el evento de dominio TemplateStateChanged.
     */
    public function transition(string $templateId, string $newStatus, string $actorId): Template;

    /**
     * Listado paginado con filtros (20 ítems por defecto en request).
     */
    public function paginateFiltered(FilterTemplatesDto $filters, int $perPage = 20): LengthAwarePaginator;

    /**
     * Crea una plantilla con los atributos dados.
     * 
     * @param  CreateTemplateDto  $dto
     */
    public function create(CreateTemplateDto $dto): Template;

    /**
     * Actualiza una plantilla con los atributos dados.
     * 
     * @param  string  $templateId
     * @param  UpdateTemplateDto  $dto
     */
    public function update(string $templateId, UpdateTemplateDto $dto): Template;

    /**
     * Elimina una plantilla físicamente (no se archiva).
     * 
     * @param  string  $templateId
     * @param  string  $actorId
     * @return bool true si se eliminó físicamente; false si solo se archivó (hay documentos asociados).
     */
    public function destroy(string $templateId, string $actorId): bool;

    /**
     * Clona una plantilla origen hacia una nueva destino.
     * 
     * @param  string  $sourceTemplateId
     * @param  string  $actorId
     */
    public function clone(string $sourceTemplateId, string $actorId): Template;
}
