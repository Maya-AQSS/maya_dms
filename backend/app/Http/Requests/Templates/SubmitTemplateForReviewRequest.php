<?php

declare(strict_types=1);

namespace App\Http\Requests\Templates;

use App\Http\Requests\Concerns\ValidatesSubmissionChangelog;
use App\Models\Template;
use Illuminate\Foundation\Http\FormRequest;

class SubmitTemplateForReviewRequest extends FormRequest
{
    use ValidatesSubmissionChangelog;

    private ?Template $resolvedTemplate = null;

    public function authorize(): bool
    {
        return $this->user()->can('submitForReview', $this->resolveTemplate());
    }

    private function resolveTemplate(): Template
    {
        if ($this->resolvedTemplate === null) {
            $this->resolvedTemplate = Template::query()->findOrFail($this->route('template'));
        }

        return $this->resolvedTemplate;
    }
}
