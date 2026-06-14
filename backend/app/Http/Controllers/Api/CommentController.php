<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\Comments\CommentableResource;
use App\Http\Concerns\ValidatesOptionalProcessContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Comments\MarkBlockCommentsReadRequest;
use App\Http\Requests\Comments\StoreCommentRequest;
use App\Http\Requests\Comments\UpdateCommentRequest;
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
        $resource = $this->resolveAndAuthorizeResource($request, 'view');

        if ($resource === null) {
            abort(404);
        }

        if (! $resource->model->isCommentingOpen()) {
            return response()->json(['data' => [], 'meta' => ['commenting_open' => false]]);
        }

        $perPage = min((int) $request->get('per_page', self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE);
        $page = $this->commentService->listForResource(
            $resource->class,
            (string) $resource->model->id,
            $resource->version,
            $perPage,
            (string) Auth::id(),
        );

        return response()->json([
            'data' => CommentResource::collection($page->items),
            'meta' => [
                'commenting_open' => true,
                'total' => $page->total,
                'per_page' => $page->perPage,
                'current_page' => $page->currentPage,
                'last_page' => $page->lastPage,
            ],
        ]);
    }

    public function store(StoreCommentRequest $request): JsonResponse
    {
        $resource = $this->resolveAndAuthorizeResource($request, 'comment');

        if ($resource === null) {
            abort(404);
        }

        if (! $resource->model->isCommentingOpen()) {
            abort(422, __('comments.closed'));
        }

        $blockableId = $request->blockableId();
        $commentDto = $this->commentService->createForResource(
            commentableType: $resource->class,
            commentableId: (string) $resource->model->id,
            commentableVersion: $resource->version,
            blockableType: $resource->blockableClass($blockableId),
            blockableId: $blockableId,
            parentId: $request->parentId(),
            authorId: (string) Auth::id(),
            body: $request->commentBody(),
        );

        return (new CommentResource($commentDto))->response()->setStatusCode(201);
    }

    public function show(string $comment): JsonResponse
    {
        $readerUserId = (string) Auth::id();
        $commentDto = $this->commentService->findOrFail($comment, $readerUserId);
        $this->authorizeCommentAccess($commentDto->source, 'view');

        return (new CommentResource($commentDto))->response();
    }

    public function update(UpdateCommentRequest $request, string $comment): JsonResponse
    {
        $readerUserId = (string) Auth::id();
        $commentDto = $this->commentService->findOrFail($comment, $readerUserId);
        $this->authorizeCommentAccess($commentDto->source, 'view');
        $this->authorize('update', $commentDto->source);

        $commentDto = $this->commentService->update(
            $comment,
            $request->commentBody(),
            $readerUserId,
            $readerUserId,
        );

        return (new CommentResource($commentDto))->response();
    }

    public function markRead(string $comment): JsonResponse
    {
        $readerUserId = (string) Auth::id();
        $commentDto = $this->commentService->findOrFail($comment, $readerUserId);
        $this->authorizeCommentAccess($commentDto->source, 'view');

        $commentDto = $this->commentService->markAsRead($comment, $readerUserId);

        return (new CommentResource($commentDto))->response();
    }

    public function markBlockRead(MarkBlockCommentsReadRequest $request): JsonResponse
    {
        $resource = $this->resolveAndAuthorizeResource($request, 'view');

        if ($resource === null) {
            abort(404);
        }

        if (! $resource->model->isCommentingOpen()) {
            abort(404);
        }

        $blockableId = $request->blockableId();
        $blockableType = $resource->blockableClass($blockableId);
        if ($blockableType === null) {
            abort(422, __('comments.invalid_block'));
        }

        $readerUserId = (string) Auth::id();
        $comments = $this->commentService->markBlockCommentsAsRead(
            commentableType: $resource->class,
            commentableId: (string) $resource->model->id,
            commentableVersion: $resource->version,
            blockableType: $blockableType,
            blockableId: $blockableId,
            userId: $readerUserId,
        );

        return response()->json([
            'data' => CommentResource::collection($comments),
        ]);
    }

    public function destroy(string $comment): JsonResponse
    {
        $commentDto = $this->commentService->findOrFail($comment);
        $this->authorizeCommentAccess($commentDto->source, 'view');
        $this->authorize('delete', $commentDto->source);

        $this->commentService->delete($comment, (string) Auth::id(), Auth::user()?->name ?? '');

        return response()->json([], 204);
    }

    /**
     * Resuelve y autoriza el recurso comentable desde los parámetros de ruta.
     * Devuelve null cuando la ruta no incluye template ni document.
     */
    private function resolveAndAuthorizeResource(Request $request, string $ability = 'comment'): ?CommentableResource
    {
        $templateId = $request->route('template');
        $documentId = $request->route('document');

        if ($templateId) {
            $model = $this->templateService->findOrFailWithoutCatalogScope($templateId);
            $this->authorize($ability, $model);
            $this->assertOptionalProcessContextMatches((string) $model->process_id);

            return new CommentableResource($model, Template::class, $model->currentVersion());
        }

        if ($documentId) {
            $model = $this->documentService->findModelOrFail($documentId);
            $this->authorize($ability, $model);
            $this->assertOptionalProcessContextMatches((string) $model->process_id);

            return new CommentableResource($model, Document::class, $model->currentVersion());
        }

        return null;
    }

    /**
     * Verifica que el usuario puede acceder al comentario vía su recurso padre.
     */
    private function authorizeCommentAccess(Comment $comment, string $ability = 'comment'): void
    {
        $commentableType = $comment->commentable_type;

        if ($commentableType === Template::class) {
            $model = $this->templateService->findOrFailWithoutCatalogScope((string) $comment->commentable_id);
            $this->loadAndAuthorizeCommentable($comment, $model, $ability);

            return;
        }

        if ($commentableType === Document::class) {
            $model = $this->documentService->findModelOrFail((string) $comment->commentable_id);
            $this->loadAndAuthorizeCommentable($comment, $model, $ability);

            return;
        }

        abort(422, __('comments.unsupported_resource'));
    }

    /**
     * Inyecta el modelo cargado en la relación (evita N+1 en policies) y verifica acceso.
     */
    private function loadAndAuthorizeCommentable(Comment $comment, Model $model, string $ability = 'comment'): void
    {
        $comment->setRelation('commentable', $model);
        $this->authorize($ability, $model);
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        if (! $model->isCommentingOpen()) {
            abort(404);
        }
    }
}
