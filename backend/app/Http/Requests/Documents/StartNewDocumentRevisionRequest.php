<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Http\Requests\Documents\Concerns\ResolvesDocumentForAuthorization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class StartNewDocumentRevisionRequest extends FormRequest
{
    use ResolvesDocumentForAuthorization;

    public function authorize(): bool
    {
        return $this->user()->can('attemptStartRevision', $this->resolveDocument());
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException(__('auth.document.new_revision_forbidden'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
