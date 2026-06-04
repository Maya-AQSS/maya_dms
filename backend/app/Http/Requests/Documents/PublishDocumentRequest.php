<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Models\Document;
use App\Support\VersionSubmissionChangelog;
use Illuminate\Foundation\Http\FormRequest;

class PublishDocumentRequest extends FormRequest
{
    private ?Document $resolvedDocument = null;

    public function authorize(): bool
    {
        return true;
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
            'changelog.required' => 'El changelog es obligatorio al publicar un documento.',
            'changelog.min' => 'El changelog es obligatorio al publicar un documento.',
        ];
    }

    private function resolveDocument(): Document
    {
        if ($this->resolvedDocument === null) {
            $this->resolvedDocument = Document::query()->findOrFail($this->route('document'));
        }

        return $this->resolvedDocument;
    }
}
