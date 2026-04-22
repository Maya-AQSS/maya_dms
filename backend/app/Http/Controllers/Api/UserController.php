<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JwtUser;
use App\Services\Contracts\UserDirectoryServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly UserDirectoryServiceInterface $userDirectoryService,
    ) {}

    /**
     * GET /api/v1/users?search={term}&per_page={n}
     *
     * Búsqueda case-insensitive por nombre, email y departamento.
     * Devuelve { data: [...] } con el campo `role` mapeado desde `department`.
     *
     * Devuelve array vacío si el término tiene menos de 2 caracteres.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof JwtUser || ! $user->hasPermission('users.search')) {
            abort(403, 'No tienes permiso para buscar usuarios.');
        }

        $search  = trim((string) $request->get('search', ''));
        $perPage = min((int) $request->get('per_page', 20), 50);

        if (mb_strlen($search) < 2) {
            return response()->json(['data' => []]);
        }

        $users = $this->userDirectoryService->searchUsers($search, $perPage);

        return response()->json(['data' => $users]);
    }

    /**
     * GET /api/v1/users/reviewer-candidates?search={term}&per_page={n}
     *
     * Devuelve los usuarios que tienen el permiso `templates.review` y, por tanto,
     * pueden ser seleccionados como revisores de una plantilla normativa.
     * El front no necesita conocer el código de permiso interno.
     *
     * `search` es opcional; si se omite devuelve todos los candidatos (hasta per_page).
     */
    public function reviewerCandidates(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof JwtUser || ! $user->hasPermission('users.search')) {
            abort(403, 'No tienes permiso para buscar usuarios.');
        }

        $search  = trim((string) $request->get('search', ''));
        $perPage = min((int) $request->get('per_page', 20), 50);

        $users = $this->userDirectoryService->searchTemplateReviewerCandidates($search, $perPage);

        return response()->json(['data' => $users]);
    }

    /**
     * GET /api/v1/users/document-reviewer-candidates?search={term}&per_page={n}
     *
     * Devuelve los usuarios que tienen el permiso `documents.review` y, por tanto,
     * pueden ser seleccionados como revisores de documentos en la plantilla.
     * El front no necesita conocer el código de permiso interno.
     *
     * `search` es opcional; si se omite devuelve todos los candidatos (hasta per_page).
     */
    public function documentReviewerCandidates(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof JwtUser || ! $user->hasPermission('users.search')) {
            abort(403, 'No tienes permiso para buscar usuarios.');
        }

        $search  = trim((string) $request->get('search', ''));
        $perPage = min((int) $request->get('per_page', 20), 50);

        $users = $this->userDirectoryService->searchDocumentReviewerCandidates($search, $perPage);

        return response()->json(['data' => $users]);
    }
}
