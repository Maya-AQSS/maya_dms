<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Http\Requests\Documents\Concerns\ResolvesDocumentForAuthorization;
use Illuminate\Foundation\Http\FormRequest;

class CloneDocumentRequest extends FormRequest
{
    use ResolvesDocumentForAuthorization;

    /**
     * Verifica si el usuario puede clonar el documento.
     */
    public function authorize(): bool
    {
        return $this->user()->can('clone', $this->resolveDocument());
    }

    /**
     * Sin payload en el clon (paridad con plantillas).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
