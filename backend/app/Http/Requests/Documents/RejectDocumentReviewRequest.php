<?php

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;

class RejectDocumentReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepara el motivo del rechazo para la validación.
     */
    protected function prepareForValidation(): void
    {
        $raw = $this->input('rejection_reason');
        if (is_string($raw)) {
            $this->merge(['rejection_reason' => trim($raw)]);
        }
    }

    /**
     * Reglas de validación para el rechazo de una revisión de un documento.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'min:5', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'Debes indicar un motivo para el rechazo.',
            'rejection_reason.min' => 'El motivo del rechazo debe tener al menos :min caracteres.',
            'rejection_reason.max' => 'El motivo del rechazo no puede superar :max caracteres.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'rejection_reason' => 'motivo del rechazo',
        ];
    }
}
