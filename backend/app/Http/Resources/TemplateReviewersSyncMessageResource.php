<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Envuelve un mensaje genérico de sincronización de revisores.
 */
class TemplateReviewersSyncMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'message' => $this->resource['message'] ?? 'Sincronización completada correctamente.',
        ];
    }
}
