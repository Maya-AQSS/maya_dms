<?php

declare(strict_types=1);

namespace App\Http\Requests\Templates;

use App\Models\Template;
use App\Support\VersionSubmissionChangelog;
use Illuminate\Foundation\Http\FormRequest;

class SubmitTemplateForReviewRequest extends FormRequest
{
    private ?Template $resolvedTemplate = null;

    public function authorize(): bool
    {
        return $this->user()->can('submitForReview', $this->resolveTemplate());
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('changelog')) {
            $this->merge(['changelog' => trim((string) $this->input('changelog'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'changelog' => ['required', 'string', 'min:1', 'max:'.VersionSubmissionChangelog::MAX_LENGTH],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'changelog.required' => 'El changelog es obligatorio al enviar a validación.',
            'changelog.min' => 'El changelog es obligatorio al enviar a validación.',
            'changelog.max' => 'El changelog no puede superar '.VersionSubmissionChangelog::MAX_LENGTH.' caracteres.',
        ];
    }

    private function resolveTemplate(): Template
    {
        if ($this->resolvedTemplate === null) {
            $this->resolvedTemplate = Template::query()->findOrFail($this->route('template'));
        }

        return $this->resolvedTemplate;
    }
}
