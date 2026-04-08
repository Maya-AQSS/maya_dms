<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentReview;
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
    public function index(Request $request, Document $document): JsonResponse
    {
        $this->authorize('review', $document);

        $reviews = $this->documentService->listReviews($document->id);

        return response()->json(['data' => $reviews]);
    }

    /**
     * Aprobar revisión de un documento.
     */
    public function approve(Request $request, Document $document, DocumentReview $review): JsonResponse
    {
        $this->authorize('review', $document);

        if ($review->document_id !== $document->id) {
            abort(404);
        }

        $actorId = $request->user()->getAuthIdentifier();
        $updated = $this->documentService->approveReview($document->id, $review->id, $actorId);

        return response()->json(['data' => $updated]);
    }

    /**
     * Rechazar revisión de un documento.
     */
    public function reject(Request $request, Document $document, DocumentReview $review): JsonResponse
    {
        $this->authorize('review', $document);

        if ($review->document_id !== $document->id) {
            abort(404);
        }

        $validated = $request->validate([
            'rejection_reason' => ['nullable', 'string'],
        ]);

        $actorId = $request->user()->getAuthIdentifier();
        $updated = $this->documentService->rejectReview(
            $document->id,
            $review->id,
            $actorId,
            $validated['rejection_reason'] ?? null,
        );

        return response()->json(['data' => $updated]);
    }
}
