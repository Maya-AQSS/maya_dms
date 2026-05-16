<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;

class StartNewDocumentRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = Document::query()->findOrFail($this->route('document'));

        return $this->user()->can('startRevision', $document);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
