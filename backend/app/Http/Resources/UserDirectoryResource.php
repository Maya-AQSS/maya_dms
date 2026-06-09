<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representa un usuario del directorio devuelto por UserDirectoryService.
 *
 * El recurso recibe arrays emitidos por UserDirectoryRepository (no modelos Eloquent).
 *
 * @property-read array{
 *     id: string,
 *     name: string|null,
 *     email: string|null,
 *     role: string|null,
 * } $resource
 */
class UserDirectoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'] ?? null,
            'name' => $this->resource['name'] ?? null,
            'email' => $this->resource['email'] ?? null,
            'role' => $this->resource['role'] ?? null,
        ];
    }
}
