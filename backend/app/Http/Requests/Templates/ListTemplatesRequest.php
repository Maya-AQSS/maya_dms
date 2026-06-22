<?php

declare(strict_types=1);

namespace App\Http\Requests\Templates;

use App\DTOs\Templates\TemplateFilterDto;
use App\Enums\TemplateVisibilityLevel;
use App\Http\Requests\Concerns\ParsesFavoriteIds;
use App\Models\Template;
use App\Repositories\Eloquent\TemplateRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\Rule;
use Maya\Http\Http\Requests\PaginatedFilterRequest;

/**
 * FormRequest para el listado paginado de plantillas.
 *
 * Extiende PaginatedFilterRequest (shared-http-laravel) añadiendo
 * las reglas de filtrado propias del dominio DMS. Reemplaza a
 * {@see IndexTemplateRequest} añadiendo soporte de paginación server-side.
 */
class ListTemplatesRequest extends PaginatedFilterRequest
{
    use ParsesFavoriteIds;

    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Template::class);
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException(__('auth.template.list_required'));
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function filterRules(): array
    {
        return [
            'process_id' => ['nullable', 'uuid', 'exists:processes,id'],
            'status' => ['nullable', 'string', 'in:draft,in_review,published,archived'],
            'visibility_level' => ['nullable', Rule::enum(TemplateVisibilityLevel::class)],
            'study_type_id' => ['nullable', 'string', 'max:255'],
            'study_id' => ['nullable', 'string', 'max:255'],
            'module_id' => ['nullable', 'string', 'max:255'],
            'team_id' => ['nullable', 'uuid', 'exists:teams,id'],
            'usable_for_documents' => ['nullable', 'boolean'],
            'favorite_ids' => ['nullable', 'string', 'max:4000'],
        ];
    }

    /**
     * Whitelist de columnas ordenables; debe coincidir con las que acepta
     * {@see TemplateRepository::applyTemplateSort()}.
     * Un sort_by fuera de la lista recibe 422 en la capa de validación.
     *
     * @return list<string>
     */
    protected function allowedSortFields(): array
    {
        return ['updated_at', 'created_at', 'name', 'delivery_deadline'];
    }

    /**
     * Convierte los parámetros validados en un DTO de filtros tipado.
     */
    public function toFilterDto(): TemplateFilterDto
    {
        return new TemplateFilterDto(
            processId: $this->input('process_id'),
            status: $this->input('status'),
            visibilityLevel: $this->input('visibility_level'),
            studyTypeId: $this->input('study_type_id'),
            studyId: $this->input('study_id'),
            moduleId: $this->input('module_id'),
            teamId: $this->input('team_id'),
            usableForDocuments: (bool) $this->input('usable_for_documents', false),
            favoriteIds: $this->parseFavoriteIds(),
            page: $this->getPage(),
            perPage: $this->getPerPage(),
            sortBy: $this->getSortBy() ?? 'updated_at',
            sortDir: $this->getSortDir(),
            search: $this->input('search'),
        );
    }
}
