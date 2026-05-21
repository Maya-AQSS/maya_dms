<?php

declare(strict_types=1);

namespace App\Http\Requests\Templates;

use App\DTOs\Templates\SyncUsersDto;
use App\Models\Template;
use App\Services\Contracts\TemplateServiceInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class SyncTemplateUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $template = $this->resolveTemplate();

        return $this->user()->can('assignReview', $template);
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Se requiere permiso para asignar revisores de plantilla.');
    }

    /**
     * Reglas de validación para la sincronización de usuarios de una plantilla.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_ids' => ['present', 'array'],
            'user_ids.*' => ['required', 'string', 'distinct', 'exists:users,id'],
        ];
    }

    /**
     * Convierte los datos validados en un DTO de sincronización de usuarios.
     */
    public function toDto(): SyncUsersDto
    {
        return new SyncUsersDto(
            userIds: $this->validated('user_ids', []),
        );
    }

    private function resolveTemplate(): Template
    {
        $id = (string) $this->route('template');

        return app(TemplateServiceInterface::class)->findModelOrFail($id);
    }
}
