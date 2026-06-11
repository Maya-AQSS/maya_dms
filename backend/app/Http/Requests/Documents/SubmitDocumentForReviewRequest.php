<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Http\Requests\Concerns\ValidatesSubmissionChangelog;
use App\Http\Requests\Documents\Concerns\ResolvesDocumentForAuthorization;
use Illuminate\Foundation\Http\FormRequest;

class SubmitDocumentForReviewRequest extends FormRequest
{
    use ResolvesDocumentForAuthorization;
    use ValidatesSubmissionChangelog;

    public function authorize(): bool
    {
        return $this->user()->can('submit', $this->resolveDocument());
    }
}
