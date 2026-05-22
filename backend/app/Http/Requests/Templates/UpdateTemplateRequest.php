<?php

declare(strict_types=1);

namespace App\Http\Requests\Templates;

use App\DTOs\Templates\UpdateTemplateDto;
use App\Enums\TemplateVisibilityLevel;
use App\Models\Template;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateTemplateRequest extends FormRequest
{
    /**
     * Verifica si el usuario puede actualizar la plantilla.
     */
    public function authorize(): bool
    {
        $template = $this->resolveTemplate();

        if ($this->has('created_by') && $this->user()->getAuthIdentifier() !== (string) $template->created_by) {
            return false;
        }

        if ($this->filled('visibility_level')) {
            return $this->user()->can('update', [$template, $this->input('visibility_level')]);
        }

        return $this->user()->can('update', $template);
    }

    /**
     * Reglas de validación para la actualización de una plantilla.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'filled', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'visibility_level' => ['sometimes', Rule::enum(TemplateVisibilityLevel::class)],
            'delivery_deadline' => ['sometimes', 'required', 'date', 'after_or_equal:today'],
            'study_type_id' => [
                'sometimes', 'nullable', 'string', 'max:255',
                'required_if:visibility_level,study_type',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value !== null && ! in_array($value, $this->user()->studyTypeIds, true)) {
                        $fail('El tipo de estudio indicado no pertenece a tu contexto académico.');
                    }
                },
            ],
            'study_id' => [
                'sometimes', 'nullable', 'string', 'max:255',
                'required_if:visibility_level,study',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value !== null && ! in_array($value, $this->user()->studyIds, true)) {
                        $fail('El estudio indicado no pertenece a tu contexto académico.');
                    }
                },
            ],
            'module_id' => [
                'sometimes', 'nullable', 'string', 'max:255',
                'required_if:visibility_level,module',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value !== null && ! in_array($value, $this->user()->moduleIds, true)) {
                        $fail('El módulo indicado no pertenece a tu contexto académico.');
                    }
                },
            ],
            'team_id' => [
                'sometimes', 'nullable', 'uuid', 'exists:teams,id',
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
            'status' => ['prohibited'],
            'review_stages' => ['sometimes', 'integer', 'min:0'],
            'review_mode' => ['sometimes', 'string', 'in:sequential,parallel'],
            'theme_id' => ['sometimes', 'nullable', 'uuid', 'exists:themes,id'],
            'created_by' => ['sometimes', 'string', 'uuid'],
        ];
    }

    /**
     * Convierte los datos validados en un DTO de actualización de plantilla.
     */
    public function toUpdateDto(): UpdateTemplateDto
    {
        return new UpdateTemplateDto(
            name: $this->input('name'),
            setName: $this->has('name'),
            description: $this->input('description'),
            setDescription: $this->has('description'),
            visibilityLevel: $this->input('visibility_level'),
            setVisibilityLevel: $this->has('visibility_level'),
            deliveryDeadline: $this->has('delivery_deadline')
                ? ($this->input('delivery_deadline') !== null ? (string) $this->input('delivery_deadline') : null)
                : null,
            setDeliveryDeadline: $this->has('delivery_deadline'),
            studyTypeId: $this->input('study_type_id'),
            setStudyTypeId: $this->has('study_type_id'),
            studyId: $this->input('study_id'),
            setStudyId: $this->has('study_id'),
            moduleId: $this->input('module_id'),
            setModuleId: $this->has('module_id'),
            teamId: $this->input('team_id'),
            setTeamId: $this->has('team_id'),
            reviewStages: $this->has('review_stages') ? (int) $this->input('review_stages') : null,
            setReviewStages: $this->has('review_stages'),
            reviewMode: $this->input('review_mode'),
            setReviewMode: $this->has('review_mode'),
            themeId: $this->input('theme_id'),
            setThemeId: $this->has('theme_id'),
            createdBy: $this->input('created_by'),
            setCreatedBy: $this->has('created_by'),
        );
    }

    /**
     * Obtiene la plantilla a partir del UUID en la ruta.
     */
    private function resolveTemplate(): Template
    {
        $id = $this->route('template');

        return Template::query()->findOrFail($id);
    }
}
