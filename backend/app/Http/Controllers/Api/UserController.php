<?php

declare(strict_types=1);

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
     * GET /api/v1/users?search={term}&per_page={n}&exclude_user_id={uuid?}
     *
     * Búsqueda case-insensitive por nombre y email.
     * Devuelve { data: [...] }; el campo `role` queda reservado (null) en este directorio.
     *
     * Devuelve array vacío si el término tiene menos de 2 caracteres.
     *
     * `exclude_user_id`: opcional; excluye ese id del resultado (p. ej. creador de plantilla en pickers).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof JwtUser || ! $user->hasPermission('users.search')) {
            abort(403, 'No tienes permiso para buscar usuarios.');
        }

        $search = trim((string) $request->get('search', ''));
        $perPage = min((int) $request->get('per_page', 20), 50);
        $excludeUserId = $this->optionalExcludeUserId($request);

        if (mb_strlen($search) < 2) {
            return response()->json(['data' => []]);
        }

        $users = $this->userDirectoryService->searchUsers($search, $perPage, $excludeUserId);

        return response()->json(['data' => $users]);
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
     */
    public function reviewerCandidates(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof JwtUser || ! $user->hasPermission('template.show')) {
            abort(403, 'No tienes permiso para buscar validadores de plantilla.');
        }

        $search = trim((string) $request->get('search', ''));
        $perPage = min((int) $request->get('per_page', 20), 50);
        $excludeUserId = $this->optionalExcludeUserId($request);

        $users = $this->userDirectoryService->searchTemplateReviewerCandidates($search, $perPage, $excludeUserId);

        return response()->json(['data' => $users]);
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
     */
    public function documentReviewerCandidates(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof JwtUser || ! $user->hasPermission('document.show')) {
            abort(403, 'No tienes permiso para buscar validadores de documento.');
        }

        $search = trim((string) $request->get('search', ''));
        $perPage = min((int) $request->get('per_page', 20), 50);
        $excludeUserId = $this->optionalExcludeUserId($request);

        $users = $this->userDirectoryService->searchDocumentReviewerCandidates($search, $perPage, $excludeUserId);

        return response()->json(['data' => $users]);
    }

    /**
     * Obtiene el ID de usuario a excluir de la búsqueda, si se ha proporcionado.
     */
    private function optionalExcludeUserId(Request $request): ?string
    {
        $raw = trim((string) $request->query('exclude_user_id', ''));

        return $raw !== '' ? $raw : null;
    }
}
