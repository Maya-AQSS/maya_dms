<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnchoredCommentRequest;
use App\Http\Resources\AnchoredCommentResource;
use App\Models\Document;
use App\Models\Template;
use App\Services\Contracts\AnchoredCommentServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Manages anchored comments — comments pinned to a TipTap position range.
 *
 * Authorization model: the route binds `{resource_type}` (string key
 * resolved via morph_map) and `{resource_id}` (UUID). EVERY mutation
 * resolves the actual model and calls `$this->authorize('update',
 * $resource)` so anchored comments inherit the resource's policy.
 *
 * Read operations require `view` on the resource. There is no way to
 * see anchored comments on a resource you cannot view.
 */
final class AnchoredCommentController extends Controller
{
    /**
     * Whitelist of polymorphic targets. Extend deliberately — passing an
     * arbitrary class name through the URL would be an IDOR vector.
     *
     * @var array<string, class-string>
     */
    private const RESOURCE_MAP = [
        'template' => Template::class,
        'document' => Document::class,
    ];

    public function __construct(
        private readonly AnchoredCommentServiceInterface $anchoredCommentService,
    ) {}

    public function index(Request $request, string $resourceType, string $resourceId): JsonResponse
    {
        $resource = $this->resolveAndAuthorize($resourceType, $resourceId, 'view');

        $anchors = $this->anchoredCommentService->listForResource($resourceType, (string) $resource->getKey());

        return response()->json(['data' => AnchoredCommentResource::collection($anchors)]);
    }

    public function store(
        AnchoredCommentRequest $request,
        string $resourceType,
        string $resourceId,
    ): JsonResponse {
        $resource = $this->resolveAndAuthorize($resourceType, $resourceId, 'update');

        $anchor = $this->anchoredCommentService->createForResource(
            $resourceType,
            (string) $resource->getKey(),
            $request->validated('comment_id'),
            $request->validated('anchor_from'),
            $request->validated('anchor_to'),
            $request->validated('anchor_text_snapshot'),
        );

        return response()->json(['data' => new AnchoredCommentResource($anchor)], 201);
    }

    public function update(
        AnchoredCommentRequest $request,
        string $resourceType,
        string $resourceId,
        string $anchorId,
    ): JsonResponse {
        $resource = $this->resolveAndAuthorize($resourceType, $resourceId, 'update');

        // Verify the anchor belongs to this resource
        $anchor = $this->anchoredCommentService->getForResource($resourceType, (string) $resource->getKey(), $anchorId);
        if ($anchor === null) {
            throw new NotFoundHttpException();
        }

        $updated = $this->anchoredCommentService->updateAnchor(
            $anchorId,
            $request->validated('anchor_from'),
            $request->validated('anchor_to'),
            $request->validated('anchor_text_snapshot'),
        );

        return response()->json(['data' => new AnchoredCommentResource($updated)]);
    }

    public function destroy(
        Request $request,
        string $resourceType,
        string $resourceId,
        string $anchorId,
    ): JsonResponse {
        $resource = $this->resolveAndAuthorize($resourceType, $resourceId, 'update');

        // Verify the anchor belongs to this resource
        $anchor = $this->anchoredCommentService->getForResource($resourceType, (string) $resource->getKey(), $anchorId);
        if ($anchor === null) {
            throw new NotFoundHttpException();
        }

        $this->anchoredCommentService->deleteAnchor($anchorId);

        return response()->json(null, 204);
    }

    private function resolveAndAuthorize(string $resourceType, string $resourceId, string $ability): \Illuminate\Database\Eloquent\Model
    {
        $class = self::RESOURCE_MAP[$resourceType] ?? null;
        if ($class === null) {
            throw new NotFoundHttpException();
        }

        /** @var \Illuminate\Database\Eloquent\Model $resource */
        $resource = $class::findOrFail($resourceId);

        $this->authorize($ability, $resource);

        return $resource;
    }
}
