<?php

declare(strict_types=1);

namespace App\Http\Requests\Templates;

use App\DTOs\Templates\CreateTemplateDto;
use App\Enums\TemplateVisibilityLevel;
use App\Models\Template;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreTemplateRequest extends FormRequest
{
    /**
     * Verifica si el usuario puede crear una plantilla.
     */
    public function authorize(): bool
    {
        $level = $this->input('visibility_level', TemplateVisibilityLevel::Personal->value);

        return $this->user()->can('create', [Template::class, $level]);
    }

    /**
     * Reglas de validación para la creación de una plantilla.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'visibility_level' => ['sometimes', Rule::enum(TemplateVisibilityLevel::class)],
            'delivery_deadline' => ['required', 'date', 'after_or_equal:today'],
            'study_type_id' => [
                'nullable', 'string', 'max:255',
                'required_if:visibility_level,study_type',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value !== null && ! in_array($value, $this->user()->studyTypeIds, true)) {
                        $fail('El tipo de estudio indicado no pertenece a tu contexto académico.');
                    }
                },
            ],
            'study_id' => [
                'nullable', 'string', 'max:255',
                'required_if:visibility_level,study',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value !== null && ! in_array($value, $this->user()->studyIds, true)) {
                        $fail('El estudio indicado no pertenece a tu contexto académico.');
                    }
                },
            ],
            'module_id' => [
                'nullable', 'string', 'max:255',
                'required_if:visibility_level,module',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value !== null && ! in_array($value, $this->user()->moduleIds, true)) {
                        $fail('El módulo indicado no pertenece a tu contexto académico.');
                    }
                },
            ],
            'team_id' => [
                'nullable', 'uuid', 'exists:teams,id',
                'required_if:visibility_level,team',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value !== null) {
                        $isMember = DB::table('team_members')
                            ->where('team_id', $value)
                            ->where('user_id', $this->user()->getAuthIdentifier())
                            ->exists();
                        if (! $isMember) {
                            $fail('No eres miembro del equipo indicado.');
                        }
                    }
                },
            ],
            'review_stages' => ['sometimes', 'integer', 'min:0'],
            'review_mode' => ['sometimes', 'string', 'in:sequential,parallel'],
            'process_id' => ['required', 'uuid', 'exists:processes,id'],
        ];
    }

    /**
     * Convierte los datos validados en un DTO de creación de plantilla.
     */
    public function toCreateDto(): CreateTemplateDto
    {
        $v = $this->validated();

        return new CreateTemplateDto(
            name: $v['name'],
            description: $v['description'] ?? null,
            visibilityLevel: $v['visibility_level'] ?? TemplateVisibilityLevel::Personal->value,
            deliveryDeadline: isset($v['delivery_deadline']) ? (string) $v['delivery_deadline'] : null,
            studyTypeId: $v['study_type_id'] ?? null,
            studyId: $v['study_id'] ?? null,
            moduleId: $v['module_id'] ?? null,
            teamId: $v['team_id'] ?? null,
            reviewStages: (int) ($v['review_stages'] ?? 0),
            reviewMode: $v['review_mode'] ?? 'parallel',
            processId: $v['process_id'],
        );
    }
}
