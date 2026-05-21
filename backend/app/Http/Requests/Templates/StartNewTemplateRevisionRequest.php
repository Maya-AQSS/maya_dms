<?php

declare(strict_types=1);

namespace App\Http\Requests\Templates;

use App\Models\Template;
use App\Services\Contracts\TemplateServiceInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class StartNewTemplateRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('startRevision', $this->resolveTemplate());
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('No puedes abrir una nueva versión de esta plantilla.');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    private function resolveTemplate(): Template
    {
        return app(TemplateServiceInterface::class)->findModelOrFail((string) $this->route('template'));
    }
}
