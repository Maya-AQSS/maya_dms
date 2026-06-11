<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Http\Requests\Concerns\ValidatesSubmissionChangelog;
use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;

class SubmitDocumentForReviewRequest extends FormRequest
{
    use ValidatesSubmissionChangelog;

    private ?Document $resolvedDocument = null;

    public function authorize(): bool
    {
        return $this->user()->can('submit', $this->resolveDocument());
    }

    private function resolveDocument(): Document
    {
        if ($this->resolvedDocument === null) {
            $this->resolvedDocument = Document::query()->findOrFail($this->route('document'));
        }

        return $this->resolvedDocument;
    }
}
