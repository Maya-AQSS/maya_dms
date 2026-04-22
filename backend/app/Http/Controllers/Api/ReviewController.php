<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\ApproveDocumentReviewRequest;
use App\Http\Requests\Documents\RejectDocumentReviewRequest;
use App\Services\Contracts\DocumentServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(
        private readonly DocumentServiceInterface $documentService,
    ) {}

    /**
     * Listar revisiones de un documento.
     */
    public function index(Request $request, string $documentId): JsonResponse
    {
        $document = $this->documentService->findOrFail($documentId);
        // Listar revisiones: participantes con acceso al documento (SoD solo aplica a aprobar/rechazar).
        $this->authorize('view', $document);

        $reviews = $this->documentService->listReviews($document->id);

        return response()->json(['data' => $reviews]);
    }

    /**
     * Aprobar revisión de un documento.
     */
    public function approve(ApproveDocumentReviewRequest $request, string $documentId, string $reviewId): JsonResponse
    {
        $document = $this->documentService->findOrFail($documentId);
        $this->authorize('review', $document);

        $actorId = (string) $request->user()->getAuthIdentifier();
        $updated = $this->documentService->approveReview(
            $document->id,
            $reviewId,
            $actorId,
            $request->validated('changelog'),
        );

        return response()->json(['data' => $updated]);
    }

    /**
     * Rechazar revisión de un documento.
     */
    public function reject(RejectDocumentReviewRequest $request, string $documentId, string $reviewId): JsonResponse
    {
        $document = $this->documentService->findOrFail($documentId);
        $this->authorize('review', $document);

        $actorId = (string) $request->user()->getAuthIdentifier();
        $updated = $this->documentService->rejectReview(
            $document->id,
            $reviewId,
            $actorId,
            $request->validated('rejection_reason'),
        );

        return response()->json(['data' => $updated]);
    }
}
