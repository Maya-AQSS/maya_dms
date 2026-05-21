<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Models\Document;
use App\Services\Contracts\DocumentServiceInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class DestroyDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->resolveDocument());
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('No puedes eliminar este documento.');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function resolveDocument(): Document
    {
        $id = (string) ($this->route('document') ?? $this->route('id'));

        return app(DocumentServiceInterface::class)->findModelOrFail($id);
    }
}
