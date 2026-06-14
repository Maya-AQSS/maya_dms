<?php

declare(strict_types=1);

namespace App\Http\Requests\TemplateBlocks;

use App\Enums\BlockState;
use App\Enums\BlockType;
use App\Http\Concerns\SanitizesBlockContent;
use App\Http\Requests\TemplateBlocks\Concerns\ResolvesTemplateForBlockAuthorization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class StoreTemplateBlockRequest extends FormRequest
{
    use ResolvesTemplateForBlockAuthorization;
    use SanitizesBlockContent;

    public function authorize(): bool
    {
        return $this->user()->can('createTemplateBlock', $this->resolveTemplate());
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException(__('auth.template_block.create_required'));
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255', function ($attr, $value, $fail) {
                if ($value === null) {
                    return;
                }
                $trimmed = trim((string) $value);
                if ($trimmed === '') {
                    $fail('El nombre del bloque no puede ser una cadena vacía.');
                } elseif (mb_strtolower($trimmed) === 'bloque sin nombre') {
                    $fail('"Bloque sin nombre" no es un nombre válido para un bloque.');
                }
            }],
            'block_type' => ['sometimes', 'string', 'in:'.implode(',', BlockType::values())],
            'theme_id' => ['sometimes', 'nullable', 'uuid', 'exists:themes,id'],
            'apply_theme' => ['sometimes', 'boolean'],
            'default_content' => ['nullable', 'array'],
            'description' => ['nullable', 'array'],
            'block_state' => ['sometimes', 'string', 'in:'.implode(',', BlockState::values())],
            'page_break_after' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
