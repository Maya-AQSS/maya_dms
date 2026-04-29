<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\ValidatesOptionalProcessContext;
use App\Http\Controllers\Controller;
use App\Services\Contracts\CommentServiceInterface;
use App\Services\Contracts\DocumentServiceInterface;
use App\Services\Contracts\TemplateServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

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
    public function store(Request $request): JsonResponse
    {
        $templateId = $request->route('template');
        $documentId = $request->route('document');

        $validated = $request->validate([
            'body' => 'required|string',
            'template_block_id' => 'nullable|uuid',
            'document_block_id' => 'nullable|uuid',
            'type' => 'nullable|string',
        ]);

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
    public function show(string $comment): JsonResponse
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
    public function destroy(string $comment): JsonResponse
    {
        $commentModel = $this->commentService->findOrFail($comment);
        $document     = $this->documentService->findOrFail($commentModel->document_id);
        $this->authorize('view', $document);
        $this->assertOptionalProcessContextMatches((string) $document->process_id);

        return response()->json(['message' => 'Not implemented'], 501);
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
}
