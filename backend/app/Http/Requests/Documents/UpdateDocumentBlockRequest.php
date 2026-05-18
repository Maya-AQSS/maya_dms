<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\DTOs\Documents\UpdateDocumentBlockDto;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'content' => ['present'],
        ];
    }

    /**
     * Transforma el request en un DTO.
     */
    public function toDto(string $documentId, string $documentBlockId, string $actorId): UpdateDocumentBlockDto
    {
        return new UpdateDocumentBlockDto(
            documentId: $documentId,
            documentBlockId: $documentBlockId,
            content: $this->input('content'),
            actorId: $actorId,
        );
    }
}
