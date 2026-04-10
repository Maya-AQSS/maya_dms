<?php

namespace App\Http\Requests\TemplateBlocks;

use App\Enums\BlockState;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTemplateBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'            => ['sometimes', 'string', 'max:50'],
            'title'           => ['sometimes', 'nullable', 'string', 'max:255'],
            'default_content' => ['sometimes', 'nullable', 'array'],
            'block_state'     => ['sometimes', 'string', 'in:'.implode(',', BlockState::values())],
            'mandatory'       => ['sometimes', 'boolean'],
            'sort_order'      => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'block_state.in' => 'El estado del bloque debe ser uno de: '.implode(', ', BlockState::values()).'. Valor recibido: :input.',
        ];
    }
}
