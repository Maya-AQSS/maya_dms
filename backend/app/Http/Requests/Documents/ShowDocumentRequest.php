<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Models\Document;
use App\Services\Contracts\DocumentServiceInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;

class ShowDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->resolveDocument());
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Se requiere permiso para ver este documento.');
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
        $id = (string) $this->route('document');
        $service = app(DocumentServiceInterface::class);

        try {
            return $service->findModelOrFail($id);
        } catch (ModelNotFoundException) {
            $document = $service->findModelOrFailWithoutUserAccess($id);
            if (! $service->hasPublishedSnapshot($document->id)) {
                abort(404);
            }

            return $document;
        }
    }
}
