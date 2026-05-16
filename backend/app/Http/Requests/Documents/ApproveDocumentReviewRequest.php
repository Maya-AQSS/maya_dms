<?php
declare(strict_types=1);

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;

class ApproveDocumentReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
