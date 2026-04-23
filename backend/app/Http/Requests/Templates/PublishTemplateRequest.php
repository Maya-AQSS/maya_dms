<?php

namespace App\Http\Requests\Templates;

use App\Models\Template;
use App\Models\TemplateVersion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublishTemplateRequest extends FormRequest
{
    /**
     * Verifica si el usuario puede publicar la plantilla.
     */
    public function authorize(): bool
    {
        $template = Template::query()->findOrFail($this->route('template'));
        $user = $this->user();

        // Creator can publish their own template directly (no-reviewer workflow)
        if ($user->getAuthIdentifier() === $template->created_by) {
            return true;
        }

        // Non-creator: SoD applies (reviewer publishes after review)
        return $user->can('review', $template);
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $template = Template::query()->findOrFail($this->route('template'));
        $hasPublishedVersions = TemplateVersion::query()
            ->where('template_id', $template->id)
            ->exists();

        return [
            'changelog' => [
                Rule::requiredIf($template->status === 'in_review' && $hasPublishedVersions),
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
}
