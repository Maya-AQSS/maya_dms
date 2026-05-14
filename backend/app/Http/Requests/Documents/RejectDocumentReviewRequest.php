<?php

namespace App\Http\Requests\Documents;

use App\Models\Comment;
use App\Models\Document;
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
     * Si el validador ha dejado algún comentario en el documento, el motivo es opcional.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rejection_reason' => $this->validatorHasCommented()
                ? ['nullable', 'string', 'max:5000']
                : ['required', 'string', 'min:5', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'Debes indicar un motivo para el rechazo o dejar un comentario en algún bloque del documento.',
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

    private function validatorHasCommented(): bool
    {
        $documentId = $this->route('document');
        if ($documentId instanceof Document) {
            $documentId = (string) $documentId->id;
        } elseif (! is_string($documentId) || $documentId === '') {
            return false;
        }

        $userId = (string) $this->user()?->getAuthIdentifier();
        if ($userId === '') {
            return false;
        }

        return Comment::withoutGlobalScopes()
            ->where('commentable_id', $documentId)
            ->where('commentable_type', Document::class)
            ->where('author_id', $userId)
            ->whereNull('deleted_at')
            ->exists();
    }
}
