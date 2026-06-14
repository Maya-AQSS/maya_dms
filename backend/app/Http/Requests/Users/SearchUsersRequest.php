<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Models\JwtUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest para la búsqueda genérica de usuarios del directorio
 * (endpoints `index` y `ownerCandidates`).
 *
 * Requiere el permiso `template.show` o `document.show` (el creador de
 * una plantilla/documento dispone de al menos uno de ellos).
 */
class SearchUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof JwtUser
            && ($user->hasPermission('template.show') || $user->hasPermission('document.show'));
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException(__('users.search.forbidden'));
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1'],
            'exclude_user_id' => ['nullable', 'uuid'],
        ];
    }

    /**
     * Término de búsqueda normalizado (trim).
     */
    public function searchTerm(): string
    {
        return trim((string) ($this->validated('search') ?? ''));
    }

    /**
     * Tamaño de página acotado a 50 (default 20).
     */
    public function perPage(): int
    {
        return min((int) ($this->validated('per_page') ?? 20), 50);
    }

    /**
     * ID de usuario a excluir del resultado, si se proporcionó.
     */
    public function excludeUserId(): ?string
    {
        $raw = trim((string) ($this->validated('exclude_user_id') ?? ''));

        return $raw !== '' ? $raw : null;
    }
}
