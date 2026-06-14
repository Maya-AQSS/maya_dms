<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Templates\SyncMessageDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Envuelve un mensaje genérico de sincronización de revisores.
 *
 * @property-read SyncMessageDto $resource
 */
class TemplateReviewersSyncMessageResource extends JsonResource
{
    /**
     * @return array<string, string>
     */
    public function toArray(Request $request): array
    {
        return [
            'message' => $this->resource->message,
        ];
    }
}
