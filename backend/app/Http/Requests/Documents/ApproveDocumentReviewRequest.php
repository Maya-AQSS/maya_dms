<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Http\Requests\Documents\Concerns\ResolvesDocumentForAuthorization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class ApproveDocumentReviewRequest extends FormRequest
{
    use ResolvesDocumentForAuthorization;

    public function authorize(): bool
    {
        return $this->user()->can('review', $this->resolveDocument());
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Se requiere permiso para revisar este documento.');
    }

    /**
     * Reglas de validación para la aprobación de una revisión de un documento.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'changelog' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
