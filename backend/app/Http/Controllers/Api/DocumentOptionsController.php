<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\Documents\DocumentDto;
use App\Http\Concerns\AttachesDocumentCanCloneMeta;
use App\Http\Concerns\ValidatesOptionalProcessContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\DocumentCreateFromModuleRequest;
use App\Http\Requests\Documents\DocumentCreationOptionsRequest;
use App\Http\Resources\DocumentResource;
use App\Services\Contracts\ApiTeamEmbedServiceInterface;
use App\Services\Contracts\DocumentServiceInterface;
use App\Services\DocumentReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints de "opciones" y consultas de estado periféricas a Document:
 * creationOptions, createFromModule, templateVersionStatus. Extracted del
 * antiguo DocumentController para cumplir B9.
 */
class DocumentOptionsController extends Controller
{
    use AttachesDocumentCanCloneMeta;
    use ValidatesOptionalProcessContext;

    public function __construct(
        private readonly DocumentServiceInterface $documentService,
        private readonly ApiTeamEmbedServiceInterface $apiTeamEmbedService,
        protected readonly DocumentReviewService $documentReviewService,
    ) {}

    /**
     * Opciones para crear una programación desde la vista de módulo.
     */
    public function creationOptions(DocumentCreationOptionsRequest $request): JsonResponse
    {
        $options = $this->documentService->creationOptionsForModule($request->validated('module_id'));
        $count = count($options);

        if ($count === 0) {
            return response()->json([
                'data' => [
                    'can_create' => false,
                    'mode' => 'none',
                    'message' => 'No hay plantillas publicadas disponibles para este módulo.',
                    'options' => [],
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'can_create' => true,
                'mode' => $count === 1 ? 'auto' : 'select',
                'message' => null,
                'options' => $options,
            ],
        ]);
    }

    /**
     * Crear una programación desde módulo con selección opcional de versión de plantilla.
     */
    public function createFromModule(DocumentCreateFromModuleRequest $request): JsonResponse
    {
        $userId = (string) $request->user()->getAuthIdentifier();
        $document = $this->documentService->createFromModule(
            $request->validated('module_id'),
            $userId,
            $request->validated('process_id'),
            $request->validated('template_version_id') ?? null,
            $request->validated('delivery_deadline'),
        );
        $this->attachCanCloneMeta($document, $request);
        $this->apiTeamEmbedService->embedOnDocument($document, $userId);
        $blocks = $this->documentService->blocksForDisplay($document);

        return response()->json([
            'data' => array_merge(
                (new DocumentResource(DocumentDto::fromModel($document)))->toArray($request),
                ['blocks' => $blocks],
            ),
        ], 201);
    }

    /**
     * GET /api/v1/documents/{document}/template-version-status
     *
     * Indica si existe una versión de plantilla publicada más reciente que la anclada al documento.
     */
    public function templateVersionStatus(Request $request, string $id): JsonResponse
    {
        $document = $this->documentService->findModelOrFail($id);
        $this->authorize('view', $document);
        $this->assertOptionalProcessContextMatches((string) $document->process_id);

        return response()->json([
            'data' => $this->documentService->templateVersionStatus($document->id),
        ]);
    }
}
