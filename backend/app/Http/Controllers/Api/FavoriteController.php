<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Template;
use App\Services\Contracts\UserFavoriteServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class FavoriteController extends Controller
{
    public function __construct(
        private readonly UserFavoriteServiceInterface $userFavoriteService,
    ) {}

    /**
     * GET /api/v1/favorites
     * Listado de IDs de plantillas y documentos marcados como favoritos.
     */
    public function index(): JsonResponse
    {
        $userId = (string) request()->user()->getAuthIdentifier();

        return response()->json([
            'data' => $this->userFavoriteService->listIdsForUser($userId),
        ]);
    }

    /**
     * POST /api/v1/favorites/templates/{template}
     * Añade una plantilla favorita al usuario.
     */
    public function storeTemplate(string $template): Response
    {
        $model = Template::query()->whereKey($template)->firstOrFail();
        Gate::authorize('view', $model);

        $this->userFavoriteService->addTemplateFavorite(
            (string) request()->user()->getAuthIdentifier(),
            $template,
        );

        return response()->noContent();
    }

    /**
     * DELETE /api/v1/favorites/templates/{template}
     * Elimina una plantilla favorita del usuario.
     */
    public function destroyTemplate(string $template): Response
    {
        $this->userFavoriteService->removeTemplateFavorite(
            (string) request()->user()->getAuthIdentifier(),
            $template,
        );

        return response()->noContent();
    }

    /**
     * POST /api/v1/favorites/documents/{document}
     * Añade un documento favorito al usuario.
     */
    public function storeDocument(string $document): Response
    {
        $model = Document::query()->whereKey($document)->firstOrFail();
        Gate::authorize('view', $model);

        $this->userFavoriteService->addDocumentFavorite(
            (string) request()->user()->getAuthIdentifier(),
            $document,
        );

        return response()->noContent();
    }

    /**
     * DELETE /api/v1/favorites/documents/{document}
     * Elimina un documento favorito del usuario.
     */
    public function destroyDocument(string $document): Response
    {
        $this->userFavoriteService->removeDocumentFavorite(
            (string) request()->user()->getAuthIdentifier(),
            $document,
        );

        return response()->noContent();
    }
}
