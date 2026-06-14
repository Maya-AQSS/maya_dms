<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\DocumentReviewerCandidatesRequest;
use App\Http\Requests\Users\OwnerCandidatesRequest;
use App\Http\Requests\Users\ReviewerCandidatesRequest;
use App\Http\Requests\Users\SearchUsersRequest;
use App\Http\Resources\UserDirectoryResource;
use App\Services\Contracts\UserDirectoryServiceInterface;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function __construct(
        private readonly UserDirectoryServiceInterface $userDirectoryService,
    ) {}

    /**
     * GET /api/v1/users?search={term}&per_page={n}&exclude_user_id={uuid?}
     *
     * Búsqueda case-insensitive por nombre y email.
     * Devuelve { data: [...] }; el campo `role` queda reservado (null) en este directorio.
     *
     * Devuelve array vacío si el término tiene menos de 2 caracteres.
     *
     * `exclude_user_id`: opcional; excluye ese id del resultado (p. ej. creador de plantilla en pickers).
     */
    public function index(SearchUsersRequest $request): AnonymousResourceCollection
    {
        $search = $request->searchTerm();

        if (mb_strlen($search) < 2) {
            return UserDirectoryResource::collection([]);
        }

        $users = $this->userDirectoryService->searchUsers(
            $search,
            $request->perPage(),
            $request->excludeUserId(),
        );

        return UserDirectoryResource::collection($users);
    }

    /**
     * GET /api/v1/users/reviewer-candidates?search={term}&per_page={n}&exclude_user_id={uuid?}
     *
     * Devuelve los usuarios que tienen el permiso `templates.review` y, por tanto,
     * pueden ser seleccionados como revisores de una plantilla normativa.
     * El front no necesita conocer el código de permiso interno.
     *
     * `search` es opcional; si se omite devuelve todos los candidatos (hasta per_page).
     *
     * `exclude_user_id`: opcional; no devuelve ese usuario (p. ej. creador de la plantilla, SoD).
     *
     * Contexto académico opcional (según visibilidad de la plantilla):
     * `visibility_level`, `study_type_id`, `study_id`, `module_id`, `team_id`.
     */
    public function reviewerCandidates(ReviewerCandidatesRequest $request): AnonymousResourceCollection
    {
        $users = $this->userDirectoryService->searchTemplateReviewerCandidates(
            $request->searchTerm(),
            $request->perPage(),
            $request->excludeUserId(),
            $request->academicFilter(),
        );

        return UserDirectoryResource::collection($users);
    }

    /**
     * GET /api/v1/users/document-reviewer-candidates?search={term}&per_page={n}&exclude_user_id={uuid?}
     *
     * Devuelve los usuarios que tienen el permiso `documents.review` y, por tanto,
     * pueden ser seleccionados como revisores de documentos en la plantilla.
     * El front no necesita conocer el código de permiso interno.
     *
     * `search` es opcional; si se omite devuelve todos los candidatos (hasta per_page).
     *
     * `exclude_user_id`: opcional; no devuelve ese usuario (p. ej. creador de la plantilla, SoD).
     *
     * Contexto académico opcional (según visibilidad de la plantilla):
     * `visibility_level`, `study_type_id`, `study_id`, `module_id`, `team_id`.
     */
    public function documentReviewerCandidates(DocumentReviewerCandidatesRequest $request): AnonymousResourceCollection
    {
        $users = $this->userDirectoryService->searchDocumentReviewerCandidates(
            $request->searchTerm(),
            $request->perPage(),
            $request->excludeUserId(),
            $request->academicFilter(),
        );

        return UserDirectoryResource::collection($users);
    }

    /**
     * GET /api/v1/users/owner-candidates?search={term}&per_page={n}
     *
     * Usuarios candidatos para ser propietarios de una plantilla o documento.
     * Requiere `template.show` (permiso que tiene el creador de la plantilla/documento).
     * Devuelve todos los usuarios del directorio que coincidan con la búsqueda.
     */
    public function ownerCandidates(OwnerCandidatesRequest $request): AnonymousResourceCollection
    {
        $search = $request->searchTerm();

        if (mb_strlen($search) < 2) {
            return UserDirectoryResource::collection([]);
        }

        $users = $this->userDirectoryService->searchUsers(
            $search,
            $request->perPage(),
            $request->excludeUserId(),
        );

        return UserDirectoryResource::collection($users);
    }
}
