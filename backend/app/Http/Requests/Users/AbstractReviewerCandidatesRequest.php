<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\DTOs\Users\ReviewerCandidateFilterDto;
use App\Models\JwtUser;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base para los buscadores de candidatos a validador (revisor).
 *
 * Los parámetros de contexto académico se aceptan por compatibilidad pero el
 * directorio filtra solo por permiso de revisión.
 *
 * Las subclases definen el permiso requerido ({@see permission()}) y el
 * mensaje de error de autorización ({@see failedAuthorization()}).
 */
abstract class AbstractReviewerCandidatesRequest extends FormRequest
{
    /**
     * Código de permiso exigido por el endpoint concreto.
     */
    abstract protected function permission(): string;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof JwtUser && $user->hasPermission($this->permission());
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
            'visibility_level' => ['nullable', 'string', 'max:255'],
            'study_type_id' => ['nullable', 'string', 'max:255'],
            'study_id' => ['nullable', 'string', 'max:255'],
            'module_id' => ['nullable', 'string', 'max:255'],
            'team_id' => ['nullable', 'uuid'],
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

    /**
     * Construye el filtro académico desde los datos validados (ignorado al buscar).
     */
    public function academicFilter(): ReviewerCandidateFilterDto
    {
        return ReviewerCandidateFilterDto::fromValidated($this->validated());
    }
}
