<?php

declare(strict_types=1);

namespace App\Http\Requests\Comments;

use App\Services\Contracts\DocumentServiceInterface;
use App\Services\Contracts\TemplateServiceInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $templateId = $this->route('template');
        if ($templateId !== null) {
            $template = app(TemplateServiceInterface::class)
                ->findOrFailWithoutCatalogScope((string) $templateId);

            return $this->user()->can('comment', $template);
        }

        $documentId = $this->route('document');
        if ($documentId !== null) {
            $document = app(DocumentServiceInterface::class)->findModelOrFail((string) $documentId);

            return $this->user()->can('comment', $document);
        }

        return false;
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException(__('auth.comment.create_required'));
    }

    /**
     * Reglas de validación.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => 'required|string|min:1|max:5000',
            'parent_id' => 'nullable|uuid|exists:comments,id,deleted_at,NULL',
            'blockable_id' => 'nullable|uuid',
        ];
    }

    /**
     * Prepara la validación.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('body')) {
            $this->merge([
                'body' => trim((string) $this->input('body')),
            ]);
        }
    }

    /**
     * Valida el bloque.
     */
    public function withValidator($validator): void
    {
        $hasLegacyBlockField = $this->has('template_block_id') || $this->has('document_block_id');

        if ($hasLegacyBlockField) {
            $validator->after(function ($validator): void {
                $validator->errors()->add('blockable_id', 'Usa blockable_id como único identificador de bloque.');
            });
        }
    }

    /**
     * Obtiene el cuerpo del comentario.
     */
    public function commentBody(): string
    {
        return (string) $this->validated('body');
    }

    /**
     * Obtiene el ID del comentario padre.
     */
    public function parentId(): ?string
    {
        $value = $this->validated('parent_id');

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Obtiene el ID del bloque del comentario.
     */
    public function blockableId(): ?string
    {
        $value = $this->validated('blockable_id');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
