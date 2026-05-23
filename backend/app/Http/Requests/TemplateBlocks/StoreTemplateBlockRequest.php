<?php

declare(strict_types=1);

namespace App\Http\Requests\TemplateBlocks;

use App\Enums\BlockKind;
use App\Enums\BlockState;
use App\Http\Concerns\SanitizesBlockContent;
use App\Http\Requests\TemplateBlocks\Concerns\ResolvesTemplateForBlockAuthorization;
use App\Models\TemplateBlock;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Validation\Validator;
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
        throw new AuthorizationException('Se requiere permiso para crear bloques en esta plantilla.');
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
            'default_content' => ['nullable', 'array'],
            'description' => ['nullable', 'array'],
            'block_state' => ['sometimes', 'string', 'in:'.implode(',', BlockState::values())],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'kind' => ['sometimes', 'string', 'in:'.implode(',', BlockKind::values())],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $kind = $this->input('kind');

            // Feature flag: si los bloques especiales están desactivados,
            // rechazar cualquier kind distinto a 'content'. Permite rollback
            // sin migración inversa — los datos existentes con kind especial
            // siguen leyéndose normalmente, solo se bloquea la creación.
            if (
                $kind !== null
                && $kind !== BlockKind::Content->value
                && ! (bool) config('dms.special_blocks_enabled')
            ) {
                $v->errors()->add(
                    'kind',
                    'Los bloques especiales no están habilitados en este entorno.'
                );

                return;
            }

            if ($kind !== BlockKind::Toc->value) {
                return;
            }

            $templateId = $this->resolveTemplate()->id ?? null;
            if ($templateId === null) {
                return;
            }

            $exists = TemplateBlock::query()
                ->where('template_id', $templateId)
                ->where('kind', BlockKind::Toc->value)
                ->exists();

            if ($exists) {
                $v->errors()->add(
                    'kind',
                    'Solo se permite un bloque de índice (TOC) por plantilla.'
                );
            }
        });
    }
}
