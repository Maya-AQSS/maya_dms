<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Http\Requests\Documents\Concerns\ResolvesDocumentForAuthorization;
use Illuminate\Foundation\Http\FormRequest;

class ShowDocumentRequest extends FormRequest
{
    use ResolvesDocumentForAuthorization;

    public function authorize(): bool
    {
        return $this->user()->can('view', $this->resolveDocument());
    }

    protected function failedAuthorization(): void
    {
        abort(404);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
