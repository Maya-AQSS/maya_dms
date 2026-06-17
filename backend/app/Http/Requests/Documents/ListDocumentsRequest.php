<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\DTOs\Documents\DocumentFilterDto;
use App\Http\Requests\Concerns\ParsesFavoriteIds;
use App\Models\Document;
use Illuminate\Auth\Access\AuthorizationException;
use Maya\Http\Http\Requests\PaginatedFilterRequest;

/**
 * FormRequest para el listado paginado de documentos.
 *
 * Extiende PaginatedFilterRequest (shared-http-laravel) añadiendo
 * las reglas de filtrado propias del dominio DMS.
 */
class ListDocumentsRequest extends PaginatedFilterRequest
{
    use ParsesFavoriteIds;

    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Document::class);
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException(__('auth.document.index_required'));
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function filterRules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'process_id' => ['nullable', 'uuid', 'exists:processes,id'],
            'status' => ['nullable', 'string', 'in:draft,in_review,published,archived'],
            'template_id' => ['nullable', 'uuid', 'exists:templates,id'],
            'created_by' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'favorite_ids' => ['nullable', 'string', 'max:4000'],
            'study_type_id' => ['nullable', 'string', 'max:255'],
            'study_id' => ['nullable', 'string', 'max:255'],
            'module_id' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Convierte los parámetros validados en un DTO de filtros tipado.
     */
    public function toFilterDto(): DocumentFilterDto
    {
        $validated = $this->safe();

        return new DocumentFilterDto(
            processId: $validated['process_id'] ?? null,
            status: $validated['status'] ?? null,
            templateId: $validated['template_id'] ?? null,
            createdBy: $validated['created_by'] ?? null,
            from: $validated['from'] ?? null,
            to: $validated['to'] ?? null,
            favoriteIds: $this->parseFavoriteIds(),
            studyTypeId: $validated['study_type_id'] ?? null,
            studyId: $validated['study_id'] ?? null,
            moduleId: $validated['module_id'] ?? null,
            page: $this->getPage(),
            perPage: $this->getPerPage(),
            sortBy: $this->getSortBy() ?? 'updated_at',
            sortDir: $this->getSortDir(),
            search: $validated['search'] ?? null,
        );
    }
}
