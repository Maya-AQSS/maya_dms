<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnchoredCommentRequest;
use App\Models\AnchoredComment;
use App\Models\Document;
use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Manages anchored comments — comments pinned to a ProseMirror position
 * range inside a TipTap document.
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

    public function index(Request $request, string $resourceType, string $resourceId): JsonResponse
    {
        $resource = $this->resolveAndAuthorize($resourceType, $resourceId, 'view');

        $anchors = AnchoredComment::query()
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resource->getKey())
            ->with('comment')
            ->orderBy('anchor_from')
            ->get();

        return response()->json(['data' => $anchors]);
    }

    public function store(
        AnchoredCommentRequest $request,
        string $resourceType,
        string $resourceId,
    ): JsonResponse {
        $resource = $this->resolveAndAuthorize($resourceType, $resourceId, 'update');

        $anchor = AnchoredComment::create([
            'comment_id' => $request->validated('comment_id'),
            'resource_type' => $resourceType,
            'resource_id' => $resource->getKey(),
            'anchor_from' => $request->validated('anchor_from'),
            'anchor_to' => $request->validated('anchor_to'),
            'anchor_text_snapshot' => $request->validated('anchor_text_snapshot'),
            'anchor_is_valid' => true,
            'anchor_last_synced_at' => now(),
        ]);

        return response()->json(['data' => $anchor], 201);
    }

    public function update(
        AnchoredCommentRequest $request,
        string $resourceType,
        string $resourceId,
        AnchoredComment $anchoredComment,
    ): JsonResponse {
        $resource = $this->resolveAndAuthorize($resourceType, $resourceId, 'update');

        if ($anchoredComment->resource_type !== $resourceType
            || (string) $anchoredComment->resource_id !== (string) $resource->getKey()) {
            throw new NotFoundHttpException();
        }

        $anchoredComment->update([
            'anchor_from' => $request->validated('anchor_from'),
            'anchor_to' => $request->validated('anchor_to'),
            'anchor_text_snapshot' => $request->validated('anchor_text_snapshot') ?? $anchoredComment->anchor_text_snapshot,
            'anchor_is_valid' => $request->validated('anchor_to') > $request->validated('anchor_from'),
            'anchor_last_synced_at' => now(),
        ]);

        return response()->json(['data' => $anchoredComment]);
    }

    public function destroy(
        Request $request,
        string $resourceType,
        string $resourceId,
        AnchoredComment $anchoredComment,
    ): JsonResponse {
        $resource = $this->resolveAndAuthorize($resourceType, $resourceId, 'update');

        if ($anchoredComment->resource_type !== $resourceType
            || (string) $anchoredComment->resource_id !== (string) $resource->getKey()) {
            throw new NotFoundHttpException();
        }

        $anchoredComment->delete();

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
