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
            'title' => ['required', 'string', 'max:255'],
            'delivery_deadline' => ['required', 'date', 'after_or_equal:today'],
            'study_type_id' => ['nullable', 'string'],
            'study_id' => ['nullable', 'string'],
            'module_id' => ['nullable', 'string'],
        ];
    }
}
