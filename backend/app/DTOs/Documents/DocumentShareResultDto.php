<?php

declare(strict_types=1);

namespace App\DTOs\Documents;

use App\Http\Resources\DocumentShareResource;
use App\Services\DocumentShareService;

/**
 * Resultado de crear/actualizar un compartido de documento.
 *
 * Las shares no tienen entidad/modelo propio; el DTO fija el contrato de salida
 * del Service ({@see DocumentShareService::upsertDocumentShare}).
 */
final readonly class DocumentShareResultDto
{
    public function __construct(
        public string $userId,
        public string $permission,
        public string $grantedBy,
    ) {}

    /**
     * Forma serializable estable para {@see DocumentShareResource}.
     *
     * @return array{user_id: string, permission: string, granted_by: string}
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'permission' => $this->permission,
            'granted_by' => $this->grantedBy,
        ];
    }
}
