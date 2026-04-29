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
            if (! Gate::forUser($request->user())->allows('view', $model)) {
                abort(404);
            }
            $this->assertOptionalProcessContextMatches((string) $model->process_id);

            return response()->json(['data' => $this->commentService->listForTemplate($templateId)]);
        }

        if ($documentId) {
            $doc = $this->documentService->findOrFail($documentId);
            $this->authorize('view', $doc);
            $this->assertOptionalProcessContextMatches((string) $doc->process_id);

            return response()->json(['data' => []]);
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
            $this->assertOptionalProcessContextMatches((string) $model->process_id);

            $comment = $this->commentService->createForTemplate($templateId, (string) Auth::id(), $validated);
            return response()->json(['data' => $comment], 201);
        }

        if ($documentId) {
            $doc = $this->documentService->findOrFail($documentId);
            $this->authorize('view', $doc);
            $this->assertOptionalProcessContextMatches((string) $doc->process_id);

            return response()->json(['message' => 'Not implemented for documents'], 501);
        }

        return response()->json(['message' => 'Resource not found'], 404);
    }

    /**
     * Mostrar comentario.
     */
    public function show(Request $request, string $comment): JsonResponse
    {
        $commentModel = $this->commentService->findOrFail($comment);
        $document     = $this->documentService->findOrFail($commentModel->document_id);
        $this->authorize('view', $document);
        $this->assertOptionalProcessContextMatches((string) $document->process_id);

        return response()->json(['data' => $commentModel]);
    }

    /**
     * Actualizar comentario.
     */
    public function update(Request $request, string $comment): JsonResponse
    {
        $commentModel = $this->commentService->findOrFail($comment);
        $document     = $this->documentService->findOrFail($commentModel->document_id);
        $this->authorize('view', $document);
        $this->assertOptionalProcessContextMatches((string) $document->process_id);

        return response()->json(['message' => 'Not implemented'], 501);
    }

    /**
     * Eliminar comentario.
     */
    public function destroy(Request $request, string $comment): JsonResponse
    {
        $commentModel = $this->commentService->findOrFail($comment);
        $document     = $this->documentService->findOrFail($commentModel->document_id);
        $this->authorize('view', $document);
        $this->assertOptionalProcessContextMatches((string) $document->process_id);

        $this->commentService->delete($comment);

        return response()->json([], 204);
    }

    /**
     * Marcar comentario como resuelto.
     */
    public function resolve(Request $request, string $comment): JsonResponse
    {
        $commentModel = $this->commentService->findOrFail($comment);
        
        // Autorización basada en el recurso al que pertenece el comentario
        if ($commentModel->template_id) {
            $model = $this->templateService->findOrFailWithoutCatalogScope((string) $commentModel->template_id);
            if (! Gate::forUser($request->user())->allows('view', $model)) {
                abort(404);
            }
            $this->assertOptionalProcessContextMatches((string) $model->process_id);
        } elseif ($commentModel->document_id) {
            $model = $this->documentService->findOrFail($commentModel->document_id);
            $this->authorize('view', $model);
            $this->assertOptionalProcessContextMatches((string) $model->process_id);
        }

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
