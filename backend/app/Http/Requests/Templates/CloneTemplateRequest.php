<?php

declare(strict_types=1);

namespace App\Http\Requests\Templates;

use App\Models\Template;
use Illuminate\Foundation\Http\FormRequest;

class CloneTemplateRequest extends FormRequest
{
    /**
     * Verifica si el usuario puede clonar la plantilla.
     */
    public function authorize(): bool
    {
        $template = Template::query()->findOrFail($this->route('template'));

        return $this->user()->can('clone', $template);
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
