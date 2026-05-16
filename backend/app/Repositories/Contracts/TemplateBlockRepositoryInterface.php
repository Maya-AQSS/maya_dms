<?php
declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Template;
use App\Models\TemplateBlock;
use Illuminate\Database\Eloquent\Collection;

interface TemplateBlockRepositoryInterface
{
    /**
     * @return Collection<int, TemplateBlock>
     */
    public function allForTemplate(string $templateId): Collection;

    public function findOrFail(string $id): TemplateBlock;

    /**
     * @param  list<string>  $ids
     * @return Collection<int, TemplateBlock>
     */
    public function findByIds(array $ids): Collection;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Template $template, array $attributes): TemplateBlock;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(TemplateBlock $block, array $attributes): TemplateBlock;

    public function delete(TemplateBlock $block): void;

    /**
     * Update por id; si no existe, inserta una fila con id fijo (forceCreate).
     * Pensado para reconstruir el conjunto de bloques publicados de una plantilla.
     *
     * @param  array<string, mixed>  $values
     */
    public function upsertByIdForTemplate(string $blockId, array $values): void;

    /**
     * Elimina bloques de una plantilla excluyendo la lista provista.
     *
     * @param  list<string>  $protectedIds
     */
    public function deleteForTemplateExcept(string $templateId, array $protectedIds): int;

    /**
     * Actualiza block_state en múltiples bloques.
     * Devuelve los bloques actualizados en el mismo orden que $ids.
     *
     * @param  list<string>  $ids
     * @param  array<string, mixed>  $attributes
     * @return Collection<int, TemplateBlock>
     */
    public function bulkUpdate(array $ids, array $attributes): Collection;

    /**
     * Reordena bloques de una plantilla de forma atómica.
     *
     * @param  list<string>  $orderedIds
     */
    public function reorderForTemplate(string $templateId, array $orderedIds): void;
}
