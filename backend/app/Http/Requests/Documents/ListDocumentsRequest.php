<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\DTOs\Documents\DocumentFilterDto;
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
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Document::class);
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Se requiere permiso document.index para listar documentos.');
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function filterRules(): array
    {
        return [
            'process_id' => ['nullable', 'uuid'],
            'status' => ['nullable', 'string', 'in:draft,in_review,published,archived'],
            'template_id' => ['nullable', 'uuid'],
            'created_by' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'favorite_ids' => ['nullable', 'string', 'max:4000'],
            'study_type_id' => ['nullable', 'uuid'],
            'study_id' => ['nullable', 'uuid'],
            'module_id' => ['nullable', 'uuid'],
        ];
    }

    /**
     * Parsea `favorite_ids` (CSV de ids de documento) a una lista, o null si vacío.
     *
     * @return list<string>|null
     */
    private function parseFavoriteIds(): ?array
    {
        $raw = $this->input('favorite_ids');
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }
        $ids = array_values(array_filter(array_map('trim', explode(',', $raw)), fn ($v) => $v !== ''));

        return $ids === [] ? null : $ids;
    }

    /**
     * Convierte los parámetros validados en un DTO de filtros tipado.
     */
    public function toFilterDto(): DocumentFilterDto
    {
        return new DocumentFilterDto(
            processId: $this->input('process_id'),
            status: $this->input('status'),
            templateId: $this->input('template_id'),
            createdBy: $this->input('created_by'),
            from: $this->input('from'),
            to: $this->input('to'),
            favoriteIds: $this->parseFavoriteIds(),
            studyTypeId: $this->input('study_type_id'),
            studyId: $this->input('study_id'),
            moduleId: $this->input('module_id'),
            page: $this->getPage(),
            perPage: $this->getPerPage(),
            sortBy: $this->getSortBy() ?? 'created_at',
            sortDir: $this->getSortDir(),
            search: $this->input('search'),
        );
    }
}
