<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\Documents\DocumentDto;
use App\Http\Concerns\AttachesDocumentCanCloneMeta;
use App\Http\Concerns\ValidatesOptionalProcessContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\ApplyTemplateMigrationRequest;
use App\Http\Requests\Documents\DelegateDocumentRequest;
use App\Http\Requests\Documents\PublishDocumentRequest;
use App\Http\Requests\Documents\StartNewDocumentRevisionRequest;
use App\Http\Requests\Documents\SubmitDocumentForReviewRequest;
use App\Http\Resources\DocumentBlockResource;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Services\Contracts\ApiTeamEmbedServiceInterface;
use App\Services\Contracts\DocumentServiceInterface;
use App\Services\DocumentReviewService;
use App\Support\WorkingRevisionConflictResolver;
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
    public function submit(SubmitDocumentForReviewRequest $request, string $document): DocumentResource
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

        return new DocumentResource(DocumentDto::fromModel($updated));
    }

    /**
     * Publicar documento.
     */
    public function publish(PublishDocumentRequest $request, string $id): DocumentResource
    {
        $document = $this->documentService->findModelOrFail($id);
        $this->authorize('publish', $document);
        $this->assertOptionalProcessContextMatches((string) $document->process_id);

        $actorId = (string) $request->user()->getAuthIdentifier();
        $updated = $this->documentService->publishDocument(
            $document->id,
            $actorId,
            $request->validated('changelog'),
            fn (Document $model) => $this->attachCanCloneMeta($model, $request),
        );

        return new DocumentResource($updated);
    }

    /**
     * Publicado → borrador (nueva versión de edición sobre el mismo expediente).
     */
    public function startNewVersion(StartNewDocumentRevisionRequest $request, string $document): JsonResponse
    {
        $model = $request->resolveDocument();
        $directAccess = $request->hasDirectDocumentAccess();

        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        $workingRevisionConflict = $this->documentService->resolveWorkingRevisionConflict($model);
        if ($workingRevisionConflict->inProgress) {
            return response()->json(
                WorkingRevisionConflictResolver::toConflictResponse($workingRevisionConflict),
                409,
            );
        }

        if (! $directAccess) {
            abort(404);
        }

        $this->authorize('startRevision', $model);

        $userId = (string) $request->user()->getAuthIdentifier();
        $updated = $this->documentService->startNewRevisionCycle(
            $model->id,
            $userId,
            function (Document $doc) use ($request, $userId): void {
                $this->attachCanCloneMeta($doc, $request);
                $this->apiTeamEmbedService->embedOnDocument($doc, $userId);
            },
        );
        $blocks = $this->documentService->blocksForDisplay($updated->id);

        return response()->json([
            'data' => array_merge(
                (new DocumentResource($updated))->toArray($request),
                ['blocks' => DocumentBlockResource::resolveDisplayList($request, $blocks)],
            ),
        ]);
    }

    /**
     * Actualiza in-situ el documento (en ciclo de nueva versión) a la versión de
     * plantilla destino: re-ancla y reconcilia bloques según las elecciones del wizard.
     */
    public function applyTemplateMigration(ApplyTemplateMigrationRequest $request, string $document): JsonResponse
    {
        $model = $this->documentService->findModelOrFail($document);
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        $userId = (string) $request->user()->getAuthIdentifier();
        $updated = $this->documentService->applyTemplateMigration(
            $request->toDto(),
            function (Document $doc) use ($request, $userId): void {
                $this->attachCanCloneMeta($doc, $request);
                $this->apiTeamEmbedService->embedOnDocument($doc, $userId);
            },
        );
        $blocks = $this->documentService->blocksForDisplay($updated->id);

        return response()->json([
            'data' => array_merge(
                (new DocumentResource($updated))->toArray($request),
                ['blocks' => DocumentBlockResource::resolveDisplayList($request, $blocks)],
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
        $blocks = $this->documentService->blocksForDisplay((string) $updated->id);

        return response()->json([
            'data' => array_merge(
                (new DocumentResource(DocumentDto::fromModel($updated)))->toArray($request),
                ['blocks' => DocumentBlockResource::resolveDisplayList($request, $blocks)],
            ),
        ]);
    }

    /**
     * Delegar documento a otro usuario.
     */
    public function delegate(DelegateDocumentRequest $request, string $id): DocumentResource
    {
        $document = $this->documentService->findModelOrFail($id);
        $this->authorize('delegate', $document);
        $this->assertOptionalProcessContextMatches((string) $document->process_id);

        $actorId = (string) $request->user()->getAuthIdentifier();
        $updated = $this->documentService->delegateOwner(
            $id,
            (string) $request->validated('new_owner_id'),
            $actorId,
            fn (Document $model) => $this->attachCanCloneMeta($model, $request),
        );

        return new DocumentResource($updated);
    }
}
