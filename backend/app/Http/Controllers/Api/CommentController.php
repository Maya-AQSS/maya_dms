<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\ValidatesOptionalProcessContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Comments\StoreCommentRequest;
use App\Models\Comment;
use App\Models\Document;
use App\Models\DocumentBlock;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Services\Contracts\CommentServiceInterface;
use App\Services\Contracts\DocumentServiceInterface;
use App\Services\Contracts\TemplateServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller
{
    use ValidatesOptionalProcessContext;

    public function __construct(
        private readonly CommentServiceInterface $commentService,
        private readonly DocumentServiceInterface $documentService,
        private readonly TemplateServiceInterface $templateService,
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
            $this->assertOptionalProcessContextMatches((string) $model->process_id);
            if ($this->isTemplateCommentsLocked($model)) {
                return response()->json(['data' => []]);
            }

            return response()->json([
                'data' => $this->commentService->listForResource(
                    Template::class,
                    (string) $model->id,
                    (int) ($model->version ?? 1),
                ),
            ]);
        }

        if ($documentId) {
            $doc = $this->documentService->findOrFail($documentId);
            $this->authorize('comment', $doc);
            $this->assertOptionalProcessContextMatches((string) $doc->process_id);
            if ($this->isDocumentCommentsLocked($doc)) {
                return response()->json(['data' => []]);
            }

            return response()->json([
                'data' => $this->commentService->listForResource(
                    Document::class,
                    (string) $doc->id,
                    (int) ($doc->current_version ?? 1),
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
        $body = $request->commentBody();
        $authorId = (string) Auth::id();

        if ($templateId) {
            $model = $this->templateService->findOrFailWithoutCatalogScope($templateId);
            $this->authorize('comment', $model);
            $this->assertOptionalProcessContextMatches((string) $model->process_id);
            if ($this->isTemplateCommentsLocked($model)) {
                abort(404);
            }

            $comment = $this->commentService->createForResource(
                commentableType: Template::class,
                commentableId: (string) $model->id,
                commentableVersion: (int) ($model->version ?? 1),
                blockableType: $blockableId !== null ? TemplateBlock::class : null,
                blockableId: $blockableId,
                parentId: $parentId,
                authorId: $authorId,
                body: $body,
            );

            return response()->json(['data' => $comment], 201);
        }

        if ($documentId) {
            $doc = $this->documentService->findOrFail($documentId);
            $this->authorize('comment', $doc);
            $this->assertOptionalProcessContextMatches((string) $doc->process_id);
            if ($this->isDocumentCommentsLocked($doc)) {
                abort(404);
            }

            $comment = $this->commentService->createForResource(
                commentableType: Document::class,
                commentableId: (string) $doc->id,
                commentableVersion: (int) ($doc->current_version ?? 1),
                blockableType: $blockableId !== null ? DocumentBlock::class : null,
                blockableId: $blockableId,
                parentId: $parentId,
                authorId: $authorId,
                body: $body,
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
        $this->authorizeViewForCommentable($commentModel);

        return response()->json(['data' => $commentModel]);
    }

    /**
     * Actualizar comentario.
     */
    public function update(Request $request, string $comment): JsonResponse
    {
        $commentModel = $this->commentService->findOrFail($comment);
        $this->authorizeViewForCommentable($commentModel);

        return response()->json(['message' => 'No está permitido actualizar un comentario en un recurso bloqueado.'], 405);
    }

    /**
     * Eliminar comentario.
     */
    public function destroy(Request $request, string $comment): JsonResponse
    {
        $commentModel = $this->commentService->findOrFail($comment);
        $this->authorizeViewForCommentable($commentModel);
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
        $this->authorizeViewForCommentable($commentModel);

        $resolved = $this->commentService->resolve($comment, (string) Auth::id());
        return response()->json(['data' => $resolved]);
    }

    /**
     * Autorizar vista para un comentario.
     */
    private function authorizeViewForCommentable(Comment $comment): void
    {
        $commentableType = ltrim((string) $comment->commentable_type, '\\');
        $isAllowed = collect(Comment::ALLOWED_COMMENTABLE_TYPES)
            ->contains(fn (string $allowed): bool => $commentableType === $allowed || is_a($commentableType, $allowed, true));

        if ($commentableType === Template::class || is_a($commentableType, Template::class, true)) {
            $model = $this->templateService->findOrFailWithoutCatalogScope((string) $comment->commentable_id);
            $this->authorize('comment', $model);
            $this->assertOptionalProcessContextMatches((string) $model->process_id);
            if ($this->isTemplateCommentsLocked($model)) {
                abort(404);
            }
            return;
        }

        if ($commentableType === Document::class || is_a($commentableType, Document::class, true)) {
            $model = $this->documentService->findOrFail((string) $comment->commentable_id);
            $this->authorize('comment', $model);
            $this->assertOptionalProcessContextMatches((string) $model->process_id);
            if ($this->isDocumentCommentsLocked($model)) {
                abort(404);
            }
            return;
        }

        if (! $isAllowed) {
            abort(422, 'Tipo de recurso de comentario no soportado.');
        }
    }

    /**
     * Verifica si los comentarios de una plantilla están bloqueados.
     */
    private function isTemplateCommentsLocked(Template $template): bool
    {
        return $template->publishedVersions()->exists();
    }

    private function isDocumentCommentsLocked(Document $document): bool
    {
        return $document->versions()->exists();
    }
}
