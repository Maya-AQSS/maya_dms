<?php

declare(strict_types=1);

namespace App\Http\Requests\Templates;

use App\DTOs\Templates\SyncUsersDto;
use App\Models\Template;
use App\Services\Contracts\TemplateServiceInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class SyncTemplateDocumentReviewersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $template = $this->resolveTemplate();

        return $this->user()->can('update', $template);
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException(__('auth.template.assign_doc_reviewers_forbidden'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_ids' => ['present', 'array'],
            'user_ids.*' => ['required', 'string', 'distinct', 'exists:users,id'],
        ];
    }

    public function toDto(): SyncUsersDto
    {
        return new SyncUsersDto(
            userIds: $this->validated('user_ids', []),
        );
    }

    private function resolveTemplate(): Template
    {
        return app(TemplateServiceInterface::class)->findModelOrFail((string) $this->route('template'));
    }
}
