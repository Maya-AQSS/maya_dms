<?php

declare(strict_types=1);

namespace App\Http\Requests\TemplateBlocks;

use App\Enums\BlockState;
use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateTemplateBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
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
            'ids.required' => 'Se requiere al menos un ID de bloque.',
            'ids.*.uuid' => 'Cada ID de bloque debe ser un UUID válido.',
            'block_state.required' => 'Debes enviar block_state para actualizar.',
            'block_state.in' => 'El estado del bloque debe ser uno de: '.implode(', ', BlockState::values()).'. Valor recibido: :input.',
        ];
    }
}
