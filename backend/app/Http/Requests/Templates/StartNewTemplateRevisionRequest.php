<?php

declare(strict_types=1);

namespace App\Http\Requests\Templates;

use App\Http\Requests\Templates\Concerns\ResolvesTemplateForAuthorization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class StartNewTemplateRevisionRequest extends FormRequest
{
    use ResolvesTemplateForAuthorization;

    public function authorize(): bool
    {
        return $this->user()->can('attemptStartRevision', $this->resolveTemplate());
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException(__('auth.template.new_revision_forbidden'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
