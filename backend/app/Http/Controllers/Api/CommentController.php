<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Comments\StoreCommentRequest;
use App\Models\Comment;
use App\Models\Document;
use App\Models\DocumentBlock;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Services\Contracts\CommentServiceInterface;
use App\Services\Contracts\DocumentServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            $this->authorize('comment', $model);
            $templateVersion = (int) $model->version;
            return response()->json([
                'data' => $this->commentService->listForResource(
                    Template::class,
                    (string) $templateId,
                    $templateVersion,
                ),
            ]);
        }

        if ($documentId) {
            $doc = $this->documentService->findOrFail($documentId);
            $this->authorize('comment', $doc);
            $documentVersion = (int) $doc->current_version;
            return response()->json([
                'data' => $this->commentService->listForResource(
                    Document::class,
                    (string) $documentId,
                    $documentVersion,
                ),
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
            $this->authorize('comment', $model);
            $templateVersion = (int) $model->version;

            $comment = $this->commentService->createForResource(
                commentableType: Template::class,
                commentableId: (string) $templateId,
                commentableVersion: $templateVersion,
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
            $documentVersion = (int) $doc->current_version;

            $comment = $this->commentService->createForResource(
                commentableType: Document::class,
                commentableId: (string) $documentId,
                commentableVersion: $documentVersion,
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
        $this->authorize('delete', $commentModel);

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

    /**
     * Autorizar vista para un comentario.
     */
    private function authorizeViewForCommentable(Request $request, Comment $comment): void
    {
        $commentableType = ltrim((string) $comment->commentable_type, '\\');
        $isAllowed = collect(Comment::ALLOWED_COMMENTABLE_TYPES)
            ->contains(fn (string $allowed): bool => $commentableType === $allowed || is_a($commentableType, $allowed, true));

        if ($commentableType === Template::class || is_a($commentableType, Template::class, true)) {
            $model = $this->templateService->findOrFailWithoutCatalogScope((string) $comment->commentable_id);
            $this->authorize('comment', $model);
            return;
        }

        if ($commentableType === Document::class || is_a($commentableType, Document::class, true)) {
            $model = $this->documentService->findOrFail((string) $comment->commentable_id);
            $this->authorize('comment', $model);
            return;
        }

        if (! $isAllowed) {
            abort(422, 'Tipo de recurso de comentario no soportado.');
        }
    }
}
