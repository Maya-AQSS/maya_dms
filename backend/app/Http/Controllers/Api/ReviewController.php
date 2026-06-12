<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Concerns\ValidatesOptionalProcessContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\ApproveDocumentReviewRequest;
use App\Http\Requests\Documents\RejectDocumentReviewRequest;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\DocumentReviewResource;
use App\Services\Contracts\DocumentServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReviewController extends Controller
{
    use ValidatesOptionalProcessContext;

    public function __construct(
        private readonly DocumentServiceInterface $documentService,
    ) {}

    /**
     * Listar revisiones de un documento.
     */
    public function index(Request $request, string $documentId): AnonymousResourceCollection
    {
        $document = $this->documentService->findModelOrFail($documentId);
        $this->authorize('view', $document);
        $this->assertOptionalProcessContextMatches((string) $document->process_id);

        $reviews = $this->documentService->listReviews($document->id);

        return DocumentReviewResource::collection($reviews);
    }

    /**
     * Aprobar revisión de un documento.
     */
    public function approve(ApproveDocumentReviewRequest $request, string $documentId, string $reviewId): JsonResponse
    {
        $document = $this->documentService->findModelOrFail($documentId);
        $this->authorize('review', $document);
        $this->assertOptionalProcessContextMatches((string) $document->process_id);

        $actorId = (string) $request->user()->getAuthIdentifier();
        $updated = $this->documentService->approveReview(
            $document->id,
            $reviewId,
            $actorId,
            $request->validated('changelog'),
        );

        return response()->json(['data' => new DocumentResource($updated)]);
    }

    /**
     * Rechazar revisión de un documento.
     */
    public function reject(RejectDocumentReviewRequest $request, string $documentId, string $reviewId): JsonResponse
    {
        $document = $this->documentService->findModelOrFail($documentId);
        $this->authorize('review', $document);
        $this->assertOptionalProcessContextMatches((string) $document->process_id);

        $actorId = (string) $request->user()->getAuthIdentifier();
        $updated = $this->documentService->rejectReview(
            $document->id,
            $reviewId,
            $actorId,
            $request->validated('rejection_reason'),
        );

        return response()->json(['data' => new DocumentResource($updated)]);
    }
}
