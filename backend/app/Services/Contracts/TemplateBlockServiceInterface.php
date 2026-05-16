<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\TemplateBlocks\BulkUpdateTemplateBlocksDto;
use App\DTOs\TemplateBlocks\TemplateBlockDto;
use App\DTOs\TemplateBlocks\UpdateTemplateBlockDto;
use App\Models\TemplateBlock;

interface TemplateBlockServiceInterface
{
    /**
     * Lista todos los bloques de una plantilla.
     *
     * @return list<TemplateBlockDto>
     */
    public function listForTemplate(string $templateId): array;

    /**
     * Devuelve el DTO de un bloque. Lanza ModelNotFoundException si no existe.
     */
    public function findOrFail(string $id): TemplateBlockDto;

    /**
     * Devuelve el modelo Eloquent de un bloque. Variante de uso interno
     * cuando el caller necesita el Model para autorización (`authorize('delete', $model)`)
     * o para encadenar a `update`/`delete` de este mismo Service. Resto de
     * consumidores deben usar `findOrFail()`.
     */
    public function findModelOrFail(string $id): TemplateBlock;

    /**
     * Carga todos los bloques por ID (IDs únicos). Lanza validación si falta alguno.
     *
     * @param  list<string>  $ids
     * @return list<TemplateBlockDto>
     */
    public function findBlocksByIdsOrFail(array $ids): array;

    /**
     * Reordena todos los bloques de una plantilla de forma atómica y registra en auditoría.
     *
     * @param  list<string>  $orderedBlockIds
     */
    public function reorderForTemplate(string $templateId, array $orderedBlockIds, string $userId): void;

    /**
     * Crea un nuevo bloque para una plantilla.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $templateId, array $attributes, string $userId): TemplateBlockDto;

    /**
     * Actualiza un bloque existente de una plantilla.
     */
    public function update(string $blockId, UpdateTemplateBlockDto $dto, string $userId): TemplateBlockDto;

    /**
     * Elimina un bloque de una plantilla.
     */
    public function delete(string $blockId, string $userId): void;

    /**
     * Actualiza múltiples bloques de una plantilla.
     *
     * @return list<TemplateBlockDto>
     */
    public function bulkUpdate(BulkUpdateTemplateBlocksDto $dto, string $userId): array;
}
