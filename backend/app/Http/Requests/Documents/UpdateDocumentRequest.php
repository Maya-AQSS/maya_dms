<?php

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'filled', 'string', 'max:255'],
            'delivery_deadline' => ['sometimes', 'date', 'after_or_equal:today'],
            'study_type_id' => ['sometimes', 'nullable', 'string'],
            'study_id' => ['sometimes', 'nullable', 'string'],
            'module_id' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
