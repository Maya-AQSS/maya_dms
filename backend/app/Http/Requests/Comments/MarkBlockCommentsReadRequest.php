<?php

declare(strict_types=1);

namespace App\Http\Requests\Comments;

use App\Services\Contracts\DocumentServiceInterface;
use App\Services\Contracts\TemplateServiceInterface;
use Illuminate\Foundation\Http\FormRequest;

class MarkBlockCommentsReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $templateId = $this->route('template');
        if ($templateId !== null) {
            $template = app(TemplateServiceInterface::class)
                ->findOrFailWithoutCatalogScope((string) $templateId);

            return $this->user()->can('view', $template);
        }

        $documentId = $this->route('document');
        if ($documentId !== null) {
            $document = app(DocumentServiceInterface::class)->findModelOrFail((string) $documentId);

            return $this->user()->can('view', $document);
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'blockable_id' => 'required|uuid',
        ];
    }

    public function blockableId(): string
    {
        return (string) $this->validated('blockable_id');
    }
}
