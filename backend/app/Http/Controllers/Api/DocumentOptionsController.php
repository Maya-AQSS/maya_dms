<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\Documents\DocumentDto;
use App\Http\Concerns\AttachesDocumentCanCloneMeta;
use App\Http\Concerns\ValidatesOptionalProcessContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\DocumentCreateFromModuleRequest;
use App\Http\Requests\Documents\DocumentCreationOptionsRequest;
use App\Http\Resources\DocumentCreateFromModuleResource;
use App\Http\Resources\DocumentCreationOptionsResource;
use App\Http\Resources\DocumentMigrationPayloadResource;
use App\Http\Resources\DocumentTemplateVersionStatusResource;
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

        $responseData = $count === 0
            ? [
                'can_create' => false,
                'mode' => 'none',
                'message' => 'No hay plantillas publicadas disponibles para este módulo.',
                'options' => [],
            ]
            : [
                'can_create' => true,
                'mode' => $count === 1 ? 'auto' : 'select',
                'message' => null,
                'options' => $options,
            ];

        return (new DocumentCreationOptionsResource($responseData))->response();
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

        $resourceData = [
            'document' => DocumentDto::fromModel($document),
            'blocks' => $blocks,
        ];

        return (new DocumentCreateFromModuleResource($resourceData))->response()->setStatusCode(201);
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

        $statusData = $this->documentService->templateVersionStatus($document->id);

        return (new DocumentTemplateVersionStatusResource($statusData))->response();
    }

    /**
     * GET /api/v1/documents/{document}/migration-payload
     *
     * Payload del paso de migración: bloques de la versión nueva de plantilla
     * comparados con la versión anclada al documento origen, con el contenido
     * real del origen. Requiere que exista una versión más reciente.
     */
    public function migrationPayload(Request $request, string $id): JsonResponse
    {
        $document = $this->documentService->findModelOrFail($id);
        $this->authorize('view', $document);
        $this->assertOptionalProcessContextMatches((string) $document->process_id);

        $payload = $this->documentService->migrationPayload($document->id);

        return (new DocumentMigrationPayloadResource($payload))->response();
    }
}
