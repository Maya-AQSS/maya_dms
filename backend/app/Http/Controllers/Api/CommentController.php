<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Comments\StoreCommentRequest;
use App\Models\Comment;
use App\Models\DocumentBlock;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Services\Contracts\CommentServiceInterface;
use App\Services\Contracts\DocumentServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    public function __construct(
        private readonly CommentServiceInterface $commentService,
        private readonly DocumentServiceInterface $documentService,
        private readonly \App\Services\Contracts\TemplateServiceInterface $templateService,
    ) {}

    /**
     * Listar comentarios.
     */
    public function index(Request $request): JsonResponse
    {
        $templateId = $request->route('template');
        $documentId = $request->route('document');

        if ($templateId) {
            $model = $this->templateService->findOrFailWithoutCatalogScope($templateId);
            if (! Gate::forUser($request->user())->allows('view', $model)) {
                abort(404);
            }
            return response()->json([
                'data' => $this->commentService->listForResource(Template::class, (string) $templateId),
            ]);
        }

        if ($documentId) {
            $doc = $this->documentService->findOrFail($documentId);
            $this->authorize('comment', $doc);
            return response()->json([
                'data' => $this->commentService->listForResource(Document::class, (string) $documentId),
            ]);
        }

        return response()->json(['data' => []]);
    }

    /**
     * Crear comentario.
     */
    public function store(StoreCommentRequest $request): JsonResponse
    {
        $templateId = $request->route('template');
        $documentId = $request->route('document');
        $blockableId = $request->blockableId();
        $parentId = $request->parentId();

        if ($templateId) {
            $model = $this->templateService->findOrFailWithoutCatalogScope($templateId);
            if (! Gate::forUser($request->user())->allows('view', $model)) {
                abort(404);
            }

            $comment = $this->commentService->createForResource(
                commentableType: Template::class,
                commentableId: (string) $templateId,
                blockableType: $blockableId ? TemplateBlock::class : null,
                blockableId: $blockableId,
                parentId: $parentId,
                authorId: (string) Auth::id(),
                body: $request->commentBody(),
            );
            return response()->json(['data' => $comment], 201);
        }

        if ($documentId) {
            $doc = $this->documentService->findOrFail($documentId);
            $this->authorize('comment', $doc);

            $comment = $this->commentService->createForResource(
                commentableType: Document::class,
                commentableId: (string) $documentId,
                blockableType: $blockableId ? DocumentBlock::class : null,
                blockableId: $blockableId,
                parentId: $parentId,
                authorId: (string) Auth::id(),
                body: $request->commentBody(),
            );

            return response()->json(['data' => $comment], 201);
        }

        return response()->json(['message' => 'Resource not found'], 404);
    }

    /**
     * Mostrar comentario.
     */
    public function show(Request $request, string $comment): JsonResponse
    {
        $commentModel = $this->commentService->findOrFail($comment);
        $this->authorizeViewForCommentable($request, $commentModel);

        return response()->json(['data' => $commentModel]);
    }

    /**
     * Eliminar comentario.
     */
    public function destroy(Request $request, string $comment): JsonResponse
    {
        $commentModel = $this->commentService->findOrFail($comment);
        $this->authorizeViewForCommentable($request, $commentModel);

        if ((string) $commentModel->author_id !== (string) Auth::id()) {
            abort(403, 'Solo el autor puede eliminar su comentario.');
        }

        $this->commentService->delete($comment);

        return response()->json([], 204);
    }

    /**
     * Marcar comentario como resuelto.
     */
    public function resolve(Request $request, string $comment): JsonResponse
    {
        $commentModel = $this->commentService->findOrFail($comment);
        $this->authorizeViewForCommentable($request, $commentModel);

        $resolved = $this->commentService->resolve($comment, (string) Auth::id());
        return response()->json(['data' => $resolved]);
    }

    private function authorizeViewForCommentable(Request $request, Comment $comment): void
    {
        if ($comment->commentable_type === Template::class) {
            $model = $this->templateService->findOrFailWithoutCatalogScope((string) $comment->commentable_id);
            if (! Gate::forUser($request->user())->allows('view', $model)) {
                abort(404);
            }

            return;
        }

        if ($comment->commentable_type === Document::class) {
            $model = $this->documentService->findOrFail((string) $comment->commentable_id);
            $this->authorize('comment', $model);
            return;
        }

        abort(422, 'Tipo de recurso de comentario no soportado.');
    }
}
