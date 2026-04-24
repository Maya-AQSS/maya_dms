<?php

namespace App\Services\Contracts;

use App\DTOs\TemplateBlocks\BulkUpdateTemplateBlocksDto;
use App\DTOs\TemplateBlocks\UpdateTemplateBlockDto;
use App\Models\TemplateBlock;
use Illuminate\Database\Eloquent\Collection;

interface TemplateBlockServiceInterface
{
    /**
     * Lista todos los bloques de una plantilla.
     * 
     * @param  string  $templateId
     * @return Collection<int, TemplateBlock>
     */
    public function listForTemplate(string $templateId): Collection;

    /**
     * Busca un bloque por ID. Lanza excepción si no existe.
     * 
     * @param  string  $id
     * @return TemplateBlock
     */
    public function findOrFail(string $id): TemplateBlock;

    /**
     * Carga todos los bloques por ID (IDs únicos). Lanza validación si falta alguno.
     *
     * @param  list<string>  $ids
     * @return Collection<int, TemplateBlock>
     */
    public function findBlocksByIdsOrFail(array $ids): Collection;

    /**
     * Reordena todos los bloques de una plantilla de forma atómica.
     *
     * @param  list<string>  $orderedBlockIds
     */
    public function reorderForTemplate(string $templateId, array $orderedBlockIds): void;

    /**
     * Crea un nuevo bloque para una plantilla.
     * 
     * @param  string  $templateId
     * @param  string  $userId
     * @param  array<string, mixed>  $attributes
     * @return TemplateBlock
     */
    public function create(string $templateId, array $attributes, string $userId): TemplateBlock;

    /**
     * Actualiza un bloque existente de una plantilla.
     * 
     * @param  string  $blockId
     * @param  UpdateTemplateBlockDto  $dto
     * @param  string  $userId
     * @return TemplateBlock
     */
    public function update(string $blockId, UpdateTemplateBlockDto $dto, string $userId): TemplateBlock;

    /**
     * Elimina un bloque de una plantilla.
     * 
     * @param  string  $blockId
     * @param  string  $userId
     * @return void
     */
    public function delete(string $blockId, string $userId): void;

    /**
     * Actualiza múltiples bloques de una plantilla.
     * 
     * @param  BulkUpdateTemplateBlocksDto  $dto
     * @param  string  $userId
     * @return Collection<int, TemplateBlock>
     */
    public function bulkUpdate(BulkUpdateTemplateBlocksDto $dto, string $userId): Collection;
}
