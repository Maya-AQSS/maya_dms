<?php

namespace App\Http\Requests\Comments;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación.
     * 
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => 'required|string',
            'parent_id' => 'nullable|uuid|exists:comments,id',
            'blockable_id' => 'nullable|string',
            // Compatibilidad temporal durante la unificación del frontend.
            'template_block_id' => 'nullable|string',
            'document_block_id' => 'nullable|string',
        ];
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
        $value = $this->validated('blockable_id')
            ?? $this->validated('template_block_id')
            ?? $this->validated('document_block_id');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
