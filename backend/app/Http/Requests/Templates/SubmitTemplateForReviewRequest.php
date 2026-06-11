<?php

declare(strict_types=1);

namespace App\Http\Requests\Templates;

use App\Http\Requests\Concerns\ValidatesSubmissionChangelog;
use App\Http\Requests\Templates\Concerns\ResolvesTemplateForAuthorization;
use Illuminate\Foundation\Http\FormRequest;

class SubmitTemplateForReviewRequest extends FormRequest
{
    use ResolvesTemplateForAuthorization;
    use ValidatesSubmissionChangelog;

    public function authorize(): bool
    {
        return $this->user()->can('submitForReview', $this->resolveTemplate());
    }
}
