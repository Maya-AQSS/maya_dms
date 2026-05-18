<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * El recurso recibe arrays (DTO-like) emitidos por ProcessService::list(),
 * no modelos Eloquent — el repositorio devuelve `list<array{...}>`.
 *
 * @property-read array{
 *     id: string,
 *     code: string,
 *     name: string,
 *     alias: string,
 *     description: string|null,
 *     process_parent_id: string|null,
 * } $resource
 */
class ProcessResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'] ?? null,
            'code' => $this->resource['code'] ?? null,
            'name' => $this->resource['name'] ?? null,
            'alias' => $this->resource['alias'] ?? null,
            'description' => $this->resource['description'] ?? null,
            'process_parent_id' => $this->resource['process_parent_id'] ?? null,
        ];
    }
}
