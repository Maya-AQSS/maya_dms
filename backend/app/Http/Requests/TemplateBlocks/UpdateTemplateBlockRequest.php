<?php

namespace App\Http\Requests\TemplateBlocks;

use App\Enums\BlockState;
use App\Http\Concerns\SanitizesBlockContent;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTemplateBlockRequest extends FormRequest
{
    use SanitizesBlockContent;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'           => ['sometimes', 'required', 'string', 'min:1', 'max:255', function ($attr, $value, $fail) {
                if (mb_strtolower(trim((string) $value)) === 'bloque sin nombre') {
                    $fail('"Bloque sin nombre" no es un nombre válido para un bloque.');
                }
            }],
            'default_content' => ['sometimes', 'nullable', 'array'],
            'description'     => ['sometimes', 'nullable', 'array'],
            'block_state'     => ['sometimes', 'string', 'in:'.implode(',', BlockState::values())],
            'sort_order'      => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
