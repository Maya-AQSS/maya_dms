<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Models\Document;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class IndexDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Document::class);
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Se requiere permiso document.index para listar documentos.');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'process_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
