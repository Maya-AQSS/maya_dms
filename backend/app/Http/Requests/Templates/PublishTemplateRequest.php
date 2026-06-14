<?php

declare(strict_types=1);

namespace App\Http\Requests\Templates;

use App\Http\Requests\Templates\Concerns\ResolvesTemplateForAuthorization;
use App\Support\VersionSubmissionChangelog;
use Illuminate\Foundation\Http\FormRequest;

class PublishTemplateRequest extends FormRequest
{
    use ResolvesTemplateForAuthorization;

    /**
     * Delega la autorización en TemplatePolicy::publish(), que aplica la regla de
     * Segregación de Funciones: el creador solo puede publicar si no hay revisores
     * asignados; en caso contrario, solo el revisor asignado puede hacerlo.
     *
     * El controlador repite la comprobación con $this->authorize('publish', $model)
     * para mantener la guardia en capa de negocio.
     */
    public function authorize(): bool
    {
        return $this->user()->can('publish', $this->resolveTemplate());
    }

    /**
     * Prepara los datos para la validación.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('changelog')) {
            $this->merge(['changelog' => trim((string) $this->input('changelog'))]);
        }
    }

    /**
     * Reglas de validación para la publicación de una plantilla.
     *
     * Changelog obligatorio en toda publicación explícita (incluida la primera versión).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'changelog' => ['required', 'string', 'min:1', 'max:'.VersionSubmissionChangelog::MAX_LENGTH],
        ];
    }

    /**
     * Mensajes de error para las reglas de validación.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'changelog.required' => __('validation.changelog.required_publish_template'),
            'changelog.min' => __('validation.changelog.required_publish_template'),
        ];
    }
}
