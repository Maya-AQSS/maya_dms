<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\Documents\DocumentDto;
use App\Http\Concerns\AttachesDocumentCanCloneMeta;
use App\Http\Concerns\ValidatesOptionalProcessContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\DelegateDocumentRequest;
use App\Http\Requests\Documents\PublishDocumentRequest;
use App\Http\Requests\Documents\SubmitDocumentForReviewRequest;
use App\Http\Requests\Documents\StartNewDocumentRevisionRequest;
use App\Http\Resources\DocumentResource;
use App\Services\Contracts\ApiTeamEmbedServiceInterface;
use App\Services\Contracts\DocumentServiceInterface;
use App\Services\DocumentReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Transiciones de estado de Document: submit, publish, startNewVersion,
 * destroyVersion, delegate. Extracted del antiguo DocumentController para
 * cumplir B9 (~5 métodos por controller).
 */
class DocumentStateController extends Controller
{
    use AttachesDocumentCanCloneMeta;
    use ValidatesOptionalProcessContext;

    public function __construct(
        private readonly DocumentServiceInterface $documentService,
        private readonly ApiTeamEmbedServiceInterface $apiTeamEmbedService,
        protected readonly DocumentReviewService $documentReviewService,
    ) {}

    /**
     * Enviar documento a revisión.
     */
    public function submit(SubmitDocumentForReviewRequest $request, string $document): JsonResponse
    {
        $model = $this->documentService->findModelOrFail($document);
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        $actorId = (string) $request->user()->getAuthIdentifier();
        $updated = $this->documentService->submitToReview(
            $model->id,
            $actorId,
            (string) $request->validated('changelog'),
        );
        $this->attachCanCloneMeta($updated, $request);

        return response()->json(['data' => (new DocumentResource(DocumentDto::fromModel($updated)))->toArray($request)]);
    }

    /**
     * Publicar documento.
     */
    public function publish(PublishDocumentRequest $request, string $id): JsonResponse
    {
        $document = $this->documentService->findModelOrFail($id);
        $this->authorize('publish', $document);
        $this->assertOptionalProcessContextMatches((string) $document->process_id);

        $actorId = (string) $request->user()->getAuthIdentifier();
        $updated = $this->documentService->publishDocument(
            $document->id,
            $actorId,
            $request->validated('changelog'),
        );
        $this->attachCanCloneMeta($updated, $request);

        return response()->json(['data' => (new DocumentResource(DocumentDto::fromModel($updated)))->toArray($request)]);
    }

    /**
     * Publicado → borrador (nueva versión de edición sobre el mismo expediente).
     */
    public function startNewVersion(StartNewDocumentRevisionRequest $request, string $document): JsonResponse
    {
        try {
            $model = $this->documentService->findModelOrFail($document);
            $directAccess = true;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $model = $this->documentService->findModelOrFailWithoutUserAccess($document);
            if (! $this->documentService->hasPublishedSnapshot($model->id)) {
                abort(404);
            }
            $directAccess = false;
        }

        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        if ($model->status !== 'published') {
            $editorName = $this->documentService->getOwnerNameForDocument($model->id);

            return response()->json([
                'message' => "{$editorName} ya está editando este documento.",
                'draft_author' => $editorName,
            ], 409);
        }

        if (! $directAccess) {
            abort(404);
        }

        $this->authorize('startRevision', $model);

        $userId = (string) $request->user()->getAuthIdentifier();
        $updated = $this->documentService->startNewRevisionCycle($model->id, $userId);
        $this->attachCanCloneMeta($updated, $request);

        $this->apiTeamEmbedService->embedOnDocument($updated, $userId);
        $blocks = $this->documentService->blocksForDisplay($updated);

        return response()->json([
            'data' => array_merge(
                (new DocumentResource(DocumentDto::fromModel($updated)))->toArray($request),
                ['blocks' => $blocks],
            ),
        ]);
    }

    /**
     * Descarta una versión no publicada (head mutable) y restaura la última publicada.
     */
    public function destroyVersion(Request $request, string $document, string $version): JsonResponse
    {
        $model = $this->documentService->findModelOrFail($document);
        $this->authorize('discard', $model);
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        $actorId = (string) $request->user()->getAuthIdentifier();
        $updated = $this->documentService->destroyVersion($model->id, $version, $actorId);
        $this->attachCanCloneMeta($updated, $request);
        $blocks = $this->documentService->blocksForDisplay($updated);

        return response()->json([
            'data' => array_merge(
                (new DocumentResource(DocumentDto::fromModel($updated)))->toArray($request),
                ['blocks' => $blocks],
            ),
        ]);
    }

    /**
     * Delegar documento a otro usuario.
     */
    public function delegate(DelegateDocumentRequest $request, string $id): JsonResponse
    {
        $document = $this->documentService->findModelOrFail($id);
        $this->authorize('delegate', $document);
        $this->assertOptionalProcessContextMatches((string) $document->process_id);

        $actorId = (string) $request->user()->getAuthIdentifier();
        $updated = $this->documentService->delegateOwner(
            $id,
            (string) $request->validated('new_owner_id'),
            $actorId,
        );
        $this->attachCanCloneMeta($updated, $request);

        return response()->json(['data' => (new DocumentResource(DocumentDto::fromModel($updated)))->toArray($request)]);
    }
}
