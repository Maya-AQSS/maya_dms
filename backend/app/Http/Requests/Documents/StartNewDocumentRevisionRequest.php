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
        return $this->user()->can('startRevision', $this->resolveDocument());
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('No puedes abrir una nueva versión de este documento.');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
