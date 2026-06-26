<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\DTOs\Documents\UpdateDocumentDto;
use App\Models\Document;
use App\Models\JwtUser;
use App\Repositories\Contracts\TeamReadRepositoryInterface;
use App\Services\Contracts\DocumentServiceInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->resolveDocument());
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException(__('auth.document.update_forbidden'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'filled', 'string', 'max:255'],
            'delivery_deadline' => ['prohibited'],
            'study_type_id' => [
                'sometimes', 'nullable', 'string', 'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $user = $this->user();
                    if ($value !== null && $user instanceof JwtUser && ! in_array($value, $user->studyTypeIds, true)) {
                        $fail('El tipo de estudio indicado no pertenece a tu contexto académico.');
                    }
                },
            ],
            'study_id' => [
                'sometimes', 'nullable', 'string', 'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $user = $this->user();
                    if ($value !== null && $user instanceof JwtUser && ! in_array($value, $user->studyIds, true)) {
                        $fail('El estudio indicado no pertenece a tu contexto académico.');
                    }
                },
            ],
            'module_id' => [
                'sometimes', 'nullable', 'string', 'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $user = $this->user();
                    if ($value !== null && $user instanceof JwtUser && ! in_array($value, $user->moduleIds, true)) {
                        $fail('El módulo indicado no pertenece a tu contexto académico.');
                    }
                },
            ],
            'team_id' => [
                'sometimes', 'nullable', 'uuid', 'exists:teams,id',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value !== null) {
                        $isMember = app(TeamReadRepositoryInterface::class)
                            ->isMember($value, (string) $this->user()->getAuthIdentifier());
                        if (! $isMember) {
                            $fail('No eres miembro del equipo indicado.');
                        }
                    }
                },
            ],
        ];
    }

    public function toDto(): UpdateDocumentDto
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return new UpdateDocumentDto(
            title: $this->stringOrNull($validated, 'title'),
            deliveryDeadline: $this->stringOrNull($validated, 'delivery_deadline'),
            studyTypeId: $this->stringOrNull($validated, 'study_type_id'),
            studyId: $this->stringOrNull($validated, 'study_id'),
            moduleId: $this->stringOrNull($validated, 'module_id'),
            teamId: $this->stringOrNull($validated, 'team_id'),
            changedFields: array_keys($validated),
        );
    }

    public function resolveDocument(): Document
    {
        $id = (string) ($this->route('document') ?? $this->route('id'));

        return app(DocumentServiceInterface::class)->findModelOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function stringOrNull(array $validated, string $key): ?string
    {
        if (! array_key_exists($key, $validated)) {
            return null;
        }
        $value = $validated[$key];

        return is_string($value) ? $value : null;
    }
}
