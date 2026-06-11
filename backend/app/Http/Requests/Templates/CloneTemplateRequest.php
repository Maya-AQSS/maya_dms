<?php

declare(strict_types=1);

namespace App\Http\Requests\Templates;

use App\Http\Requests\Templates\Concerns\ResolvesTemplateForAuthorization;
use Illuminate\Foundation\Http\FormRequest;

class CloneTemplateRequest extends FormRequest
{
    use ResolvesTemplateForAuthorization;

    /**
     * Verifica si el usuario puede clonar la plantilla.
     */
    public function authorize(): bool
    {
        return $this->user()->can('clone', $this->resolveTemplate());
    }

    /**
     * Reglas de validación para la clonación de una plantilla.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
