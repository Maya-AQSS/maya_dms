<?php

namespace App\Http\Requests\TemplateBlocks;

use App\Enums\BlockState;
use App\Http\Concerns\SanitizesBlockContent;
use Illuminate\Foundation\Http\FormRequest;

class StoreTemplateBlockRequest extends FormRequest
{
    use SanitizesBlockContent;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'           => ['nullable', 'string', 'max:255'],
            'default_content' => ['nullable', 'array'],
            'description'     => ['nullable', 'array'],
            'block_state'     => ['sometimes', 'string', 'in:'.implode(',', BlockState::values())],
            'sort_order'      => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
