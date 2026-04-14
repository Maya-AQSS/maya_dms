<?php

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
            'ids'         => ['required', 'array', 'min:1'],
            'ids.*'       => ['required', 'uuid'],
            'block_state' => ['sometimes', 'string', 'in:'.implode(',', BlockState::values()), 'required_without:mandatory'],
            'mandatory'   => ['sometimes', 'boolean', 'required_without:block_state'],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required'        => 'Se requiere al menos un ID de bloque.',
            'ids.*.uuid'          => 'Cada ID de bloque debe ser un UUID válido.',
            'block_state.required_without' => 'Debes enviar block_state o mandatory para actualizar.',
            'block_state.in'      => 'El estado del bloque debe ser uno de: '.implode(', ', BlockState::values()).'. Valor recibido: :input.',
            'mandatory.required_without' => 'Debes enviar mandatory o block_state para actualizar.',
        ];
    }
}
