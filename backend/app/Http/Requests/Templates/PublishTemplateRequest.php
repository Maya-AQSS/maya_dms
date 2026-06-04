<?php

declare(strict_types=1);

namespace App\Http\Requests\Templates;

use App\Models\Template;
use App\Support\VersionSubmissionChangelog;
use Illuminate\Foundation\Http\FormRequest;

class PublishTemplateRequest extends FormRequest
{
    private ?Template $resolvedTemplate = null;

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
            'changelog.required' => 'El changelog es obligatorio al publicar una plantilla.',
            'changelog.min' => 'El changelog es obligatorio al publicar una plantilla.',
        ];
    }

    /**
     * Resuelve la plantilla.
     */
    private function resolveTemplate(): Template
    {
        if ($this->resolvedTemplate === null) {
            $this->resolvedTemplate = Template::query()->findOrFail($this->route('template'));
        }

        return $this->resolvedTemplate;
    }
}
