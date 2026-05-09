<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\ValidatesOptionalProcessContext;
use App\Http\Controllers\Controller;
use App\Http\DTO\CommentableResource;
use App\Http\Requests\Comments\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Document;
use App\Models\Template;
use App\Services\Contracts\CommentServiceInterface;
use App\Services\Contracts\DocumentServiceInterface;
use App\Services\Contracts\TemplateServiceInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller
{
    use ValidatesOptionalProcessContext;

    private const int DEFAULT_PER_PAGE = 100;
    private const int MAX_PER_PAGE = 200;

    public function __construct(
        private readonly CommentServiceInterface $commentService,
        private readonly DocumentServiceInterface $documentService,
        private readonly TemplateServiceInterface $templateService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $resource = $this->resolveAndAuthorizeResource($request);

        if ($resource === null) {
            abort(404);
        }

        if (! $resource->model->isCommentingOpen()) {
            return response()->json(['data' => [], 'meta' => ['commenting_open' => false]]);
        }

        $perPage = min((int) $request->get('per_page', self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE);
        $paginator = $this->commentService->listForResource(
            $resource->class,
            (string) $resource->model->id,
            $resource->version,
            $perPage,
        );

        return response()->json([
            'data' => CommentResource::collection($paginator->items()),
            'meta' => [
                'commenting_open' => true,
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(StoreCommentRequest $request): JsonResponse
    {
        $resource = $this->resolveAndAuthorizeResource($request);

        if ($resource === null) {
            abort(404);
        }

        if (! $resource->model->isCommentingOpen()) {
            abort(422, 'Los comentarios están cerrados para este recurso.');
        }

        $blockableId = $request->blockableId();
        $comment = $this->commentService->createForResource(
            commentableType: $resource->class,
            commentableId: (string) $resource->model->id,
            commentableVersion: $resource->version,
            blockableType: $resource->blockableClass($blockableId),
            blockableId: $blockableId,
            parentId: $request->parentId(),
            authorId: (string) Auth::id(),
            body: $request->commentBody(),
        );

        $comment->loadMissing('author');
        return (new CommentResource($comment))->response()->setStatusCode(201);
    }

    public function show(string $comment): JsonResponse
    {
        $commentModel = $this->commentService->findOrFail($comment);
        $this->authorizeCommentAccess($commentModel);

        return (new CommentResource($commentModel))->response();
    }

    public function destroy(string $comment): JsonResponse
    {
        $commentModel = $this->commentService->findOrFail($comment);
        $this->authorizeCommentAccess($commentModel);
        $this->authorize('delete', $commentModel);

        $this->commentService->delete($commentModel);

        return response()->json([], 204);
    }

    public function resolve(Request $request, string $comment): JsonResponse
    {
        $commentModel = $this->commentService->findOrFail($comment);
        $this->authorizeCommentAccess($commentModel);
        $this->authorize('resolve', $commentModel);

        $resolved = $this->commentService->resolve($commentModel, (string) Auth::id());
        return (new CommentResource($resolved))->response();
    }

    /**
     * Resuelve y autoriza el recurso comentable desde los parámetros de ruta.
     * Devuelve null cuando la ruta no incluye template ni document.
     */
    private function resolveAndAuthorizeResource(Request $request): ?CommentableResource
    {
        $templateId = $request->route('template');
        $documentId = $request->route('document');

        if ($templateId) {
            $model = $this->templateService->findOrFailWithoutCatalogScope($templateId);
            $this->authorize('comment', $model);
            $this->assertOptionalProcessContextMatches((string) $model->process_id);
            return new CommentableResource($model, Template::class, $model->currentVersion());
        }

        if ($documentId) {
            $model = $this->documentService->findOrFail($documentId);
            $this->authorize('comment', $model);
            $this->assertOptionalProcessContextMatches((string) $model->process_id);
            return new CommentableResource($model, Document::class, $model->currentVersion());
        }

        return null;
    }

    /**
     * Verifica que el usuario puede acceder al comentario vía su recurso padre.
     */
    private function authorizeCommentAccess(Comment $comment): void
    {
        $commentableType = $comment->commentable_type;

        if ($commentableType === Template::class) {
            $model = $this->templateService->findOrFailWithoutCatalogScope((string) $comment->commentable_id);
            $this->loadAndAuthorizeCommentable($comment, $model);
            return;
        }

        if ($commentableType === Document::class) {
            $model = $this->documentService->findOrFail((string) $comment->commentable_id);
            $this->loadAndAuthorizeCommentable($comment, $model);
            return;
        }

        abort(422, 'Tipo de recurso de comentario no soportado.');
    }

    /**
     * Inyecta el modelo cargado en la relación (evita N+1 en policies) y verifica acceso.
     */
    private function loadAndAuthorizeCommentable(Comment $comment, Model $model): void
    {
        $comment->setRelation('commentable', $model);
        $this->authorize('comment', $model);
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        if (! $model->isCommentingOpen()) {
            abort(404);
        }
    }
}
