<?php

declare(strict_types=1);

namespace App\Http\Requests\Templates;

use App\Models\EntityVersion;
use App\Models\Template;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
     * El changelog es obligatorio a partir de la segunda versión publicada (cuando ya existe
     * al menos una fila publicada en entity_versions). La primera publicación puede omitir changelog;
     * el servicio usa entonces un texto por defecto genérico.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $template = $this->resolveTemplate();
        $hasPublishedVersions = EntityVersion::query()
            ->where('versionable_type', Template::class)
            ->where('versionable_id', $template->id)
            ->where('status', 'published')
            ->exists();

        return [
            'changelog' => [
                Rule::requiredIf($hasPublishedVersions),
                'nullable',
                'string',
                'min:1',
            ],
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
