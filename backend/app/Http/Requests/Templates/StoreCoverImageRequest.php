<?php

declare(strict_types=1);

namespace App\Http\Requests\Templates;

use App\Models\Template;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Subida de imagen para un bloque de portada de una plantilla. Autoriza con la
 * misma policy que editar la plantilla. Sólo archivo (sin URL remota) y sin SVG
 * para reducir superficie XSS.
 */
class StoreCoverImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $template = Template::query()->findOrFail($this->route('template'));

        return $this->user()->can('update', $template);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:10240'],
        ];
    }
}
