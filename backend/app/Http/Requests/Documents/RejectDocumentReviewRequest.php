<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Http\Requests\Documents\Concerns\ResolvesDocumentForAuthorization;
use App\Models\Document;
use App\Repositories\Contracts\CommentRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class RejectDocumentReviewRequest extends FormRequest
{
    use ResolvesDocumentForAuthorization;

    public function authorize(): bool
    {
        return $this->user()->can('review', $this->resolveDocument());
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException(__('auth.document.review_required'));
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
            'rejection_reason.required' => __('validation.rejection_reason.required'),
            'rejection_reason.min' => __('validation.rejection_reason.min'),
            'rejection_reason.max' => __('validation.rejection_reason.max'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'rejection_reason' => __('validation.attributes.rejection_reason'),
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

        return app(CommentRepositoryInterface::class)->authorHasActiveCommentOnCommentable(
            Document::class,
            $documentId,
            $userId,
        );
    }
}
