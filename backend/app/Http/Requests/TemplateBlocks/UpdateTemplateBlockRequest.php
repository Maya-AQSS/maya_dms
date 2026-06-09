<?php

declare(strict_types=1);

namespace App\Http\Requests\TemplateBlocks;

use App\DTOs\TemplateBlocks\UpdateTemplateBlockDto;
use App\Enums\BlockState;
use App\Enums\BlockType;
use App\Http\Concerns\SanitizesBlockContent;
use App\Http\Requests\TemplateBlocks\Concerns\ResolvesTemplateForBlockAuthorization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTemplateBlockRequest extends FormRequest
{
    use ResolvesTemplateForBlockAuthorization;
    use SanitizesBlockContent;

    public function authorize(): bool
    {
        return $this->user()->can('updateTemplateBlock', $this->resolveTemplate());
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Se requiere permiso para actualizar bloques de esta plantilla.');
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'min:1', 'max:255', function ($attr, $value, $fail) {
                if (mb_strtolower(trim((string) $value)) === 'bloque sin nombre') {
                    $fail('"Bloque sin nombre" no es un nombre válido para un bloque.');
                }
            }],
            'block_type' => ['sometimes', 'string', 'in:'.implode(',', BlockType::values())],
            'theme_id' => ['sometimes', 'nullable', 'uuid', 'exists:themes,id'],
            'apply_theme' => ['sometimes', 'boolean'],
            'default_content' => ['sometimes', 'nullable', 'array'],
            'description' => ['sometimes', 'nullable', 'array'],
            'block_state' => ['sometimes', 'string', 'in:'.implode(',', BlockState::values())],
            'page_break_after' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * Empaqueta los campos validados en el DTO que consume `TemplateBlockService::update`.
     * Cada `set_*` indica si el cliente envió ese atributo (presencia ≠ valor).
     */
    public function toDto(): UpdateTemplateBlockDto
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return new UpdateTemplateBlockDto(
            title: $validated['title'] ?? null,
            set_title: $this->has('title'),
            default_content: $validated['default_content'] ?? null,
            set_default_content: $this->has('default_content'),
            sort_order: $validated['sort_order'] ?? null,
            set_sort_order: $this->has('sort_order'),
            block_state: $validated['block_state'] ?? null,
            set_block_state: $this->has('block_state'),
            description: $validated['description'] ?? null,
            set_description: $this->has('description'),
            block_type: $validated['block_type'] ?? null,
            set_block_type: $this->has('block_type'),
            page_break_after: array_key_exists('page_break_after', $validated) ? (bool) $validated['page_break_after'] : null,
            set_page_break_after: $this->has('page_break_after'),
            theme_id: $validated['theme_id'] ?? null,
            set_theme_id: $this->has('theme_id'),
            apply_theme: array_key_exists('apply_theme', $validated) ? (bool) $validated['apply_theme'] : null,
            set_apply_theme: $this->has('apply_theme'),
        );
    }
}
