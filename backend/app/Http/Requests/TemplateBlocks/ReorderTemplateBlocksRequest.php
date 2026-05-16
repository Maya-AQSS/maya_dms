<?php

declare(strict_types=1);

namespace App\Http\Requests\TemplateBlocks;

use Illuminate\Foundation\Http\FormRequest;

class ReorderTemplateBlocksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para el reordenamiento de bloques de una plantilla.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'block_ids' => ['required', 'array', 'min:1'],
            'block_ids.*' => ['required', 'string', 'uuid', 'distinct'],
        ];
    }
}
