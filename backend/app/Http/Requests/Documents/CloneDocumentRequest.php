<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;

class CloneDocumentRequest extends FormRequest
{
    /**
     * Verifica si el usuario puede clonar el documento.
     */
    public function authorize(): bool
    {
        $document = Document::query()->findOrFail($this->route('document'));

        return $this->user()->can('clone', $document);
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
