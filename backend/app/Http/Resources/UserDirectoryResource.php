<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Users\UserSummaryDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representa un usuario del directorio devuelto por UserDirectoryService.
 *
 * @property-read UserSummaryDto $resource
 */
class UserDirectoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'role' => $this->resource->role,
        ];
    }
}
