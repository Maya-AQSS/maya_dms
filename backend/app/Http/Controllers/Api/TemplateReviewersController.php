<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Concerns\ValidatesOptionalProcessContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Templates\SyncTemplateUsersRequest;
use App\Services\Contracts\TemplateServiceInterface;
use Illuminate\Http\JsonResponse;

/**
 * Sincronización del set de revisores de Template (normativa) y del pool
 * de posibles revisores de documentos generados desde la plantilla. Split
 * de {@see TemplateController} para cumplir B9.
 */
class TemplateReviewersController extends Controller
{
    use ValidatesOptionalProcessContext;

    public function __construct(
        private readonly TemplateServiceInterface $templateService,
    ) {}

    /**
     * Sincroniza los revisores de la plantilla normativa.
     */
    public function syncReviewers(SyncTemplateUsersRequest $request, string $template): JsonResponse
    {
        $model = $this->templateService->findModelOrFail($template);
        $this->authorize('update', $model);
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        $this->templateService->syncReviewers($model->id, $request->toDto());

        return response()->json(['message' => 'Revisores de plantilla sincronizados correctamente.']);
    }

    /**
     * Sincroniza el pool de posibles revisores de documentos generados desde la plantilla.
     */
    public function syncDocumentReviewers(SyncTemplateUsersRequest $request, string $template): JsonResponse
    {
        $model = $this->templateService->findModelOrFail($template);
        $this->authorize('update', $model);
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        $this->templateService->syncDocumentReviewers($model->id, $request->toDto());

        return response()->json(['message' => 'Validadores de documento sincronizados correctamente.']);
    }
}
