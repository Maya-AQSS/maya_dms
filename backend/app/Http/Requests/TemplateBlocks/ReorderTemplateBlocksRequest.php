<?php

declare(strict_types=1);

namespace App\Http\Requests\TemplateBlocks;

use App\Http\Requests\TemplateBlocks\Concerns\ResolvesTemplateForBlockAuthorization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class ReorderTemplateBlocksRequest extends FormRequest
{
    use ResolvesTemplateForBlockAuthorization;

    public function authorize(): bool
    {
        return $this->user()->can('updateTemplateBlock', $this->resolveTemplate());
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Se requiere permiso para reordenar bloques de esta plantilla.');
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
