<?php

declare(strict_types=1);

namespace App\Http\Requests\Templates;

use App\DTOs\Templates\SyncUsersDto;
use Illuminate\Foundation\Http\FormRequest;

class SyncTemplateUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
}
