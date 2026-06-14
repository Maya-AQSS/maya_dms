<?php

declare(strict_types=1);

namespace App\Http\Requests\TemplateBlocks;

use App\Enums\BlockState;
use App\Http\Requests\TemplateBlocks\Concerns\ResolvesTemplateForBlockAuthorization;
use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateTemplateBlockRequest extends FormRequest
{
    use ResolvesTemplateForBlockAuthorization;

    /**
     * Autoriza la actualización masiva resolviendo TODAS las plantillas a las que
     * pertenecen los bloques (`ids.*`) y comprobando `updateTemplateBlock` en cada una.
     *
     * Si el payload `ids` es inválido (no array o vacío) se delega en {@see rules()}
     * para que devuelva 422; no se emite 403 por input malformado.
     */
    public function authorize(): bool
    {
        $ids = $this->input('ids');

        if (! is_array($ids) || $ids === []) {
            return true;
        }

        /** @var list<string> $blockIds */
        $blockIds = array_values(array_map(static fn ($id): string => (string) $id, $ids));

        $templateIds = $this->templateIdsForBlockIds($blockIds);
        $templates = $this->resolveTemplatesByIds($templateIds);
        $user = $this->user();

        foreach ($templateIds as $templateId) {
            $template = $templates->get($templateId);

            // Plantilla inexistente o fuera del alcance del usuario: denegar.
            if ($template === null || ! $user->can('updateTemplateBlock', $template)) {
                return false;
            }
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'uuid'],
            'block_state' => ['required', 'string', 'in:'.implode(',', BlockState::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => __('validation.block_ids.required'),
            'ids.*.uuid' => __('validation.block_ids.uuid'),
            'block_state.required' => __('validation.block_state.required'),
            'block_state.in' => __('validation.block_state.in', ['values' => implode(', ', BlockState::values())]),
        ];
    }
}
