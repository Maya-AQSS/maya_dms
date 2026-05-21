<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\DTOs\Documents\UpdateDocumentBlockDto;
use App\Http\Requests\Documents\Concerns\ResolvesDocumentForAuthorization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentBlockRequest extends FormRequest
{
    use ResolvesDocumentForAuthorization;

    public function authorize(): bool
    {
        return $this->user()->can('updateDocumentBlock', $this->resolveDocument());
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Se requiere permiso para actualizar bloques de este documento.');
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
