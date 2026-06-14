<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Concerns\AttachesCanCloneMeta;
use App\Http\Concerns\ResolvesApiEmbeddedTeam;
use App\Http\Concerns\ValidatesOptionalProcessContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Templates\PublishTemplateRequest;
use App\Http\Requests\Templates\StartNewTemplateRevisionRequest;
use App\Http\Requests\Templates\SubmitTemplateForReviewRequest;
use App\Http\Resources\TemplateResource;
use App\Models\Template;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Services\Contracts\ApiTeamEmbedServiceInterface;
use App\Services\Contracts\TemplateServiceInterface;
use App\Support\WorkingRevisionConflictResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Transiciones de estado de Template: submitForReview, rejectReview,
 * approveReview, publish, startNewVersion, destroyVersion. Split de
 * {@see TemplateController} para cumplir B9.
 */
class TemplateStateController extends Controller
{
    use AttachesCanCloneMeta;
    use ResolvesApiEmbeddedTeam;
    use ValidatesOptionalProcessContext;

    public function __construct(
        private readonly TemplateServiceInterface $templateService,
        private readonly TemplateRepositoryInterface $templateRepository,
        private readonly ApiTeamEmbedServiceInterface $apiTeamEmbedService,
    ) {}

    /**
     * Borrador → en revisión (autor o quien puede editar).
     */
    public function submitForReview(SubmitTemplateForReviewRequest $request, string $template): TemplateResource
    {
        $model = $this->templateService->findModelOrFail($template);
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        $updated = $this->templateService->submitForReview(
            $model->id,
            (string) Auth::id(),
            (string) $request->validated('changelog'),
            fn (Template $template) => $this->attachCanCloneMeta($template, $request),
        );

        return new TemplateResource($updated);
    }

    /**
     * En revisión → rechazada (revisor). El creador puede editar y reenviar.
     */
    public function rejectReview(Request $request, string $template): TemplateResource
    {
        $model = $this->templateService->findModelOrFail($template);
        $this->authorize('review', $model);
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        $updated = $this->templateService->rejectReview(
            $model->id,
            (string) Auth::id(),
            fn (Template $template) => $this->attachCanCloneMeta($template, $request),
        );

        return new TemplateResource($updated);
    }

    /**
     * En revisión → aprobación del revisor activo.
     *
     * Si todos los revisores han aprobado, la plantilla se publica automáticamente.
     * En modo secuencial verifica que los stages anteriores estén aprobados.
     */
    public function approveReview(Request $request, string $template): TemplateResource
    {
        $model = $this->templateService->findModelOrFail($template);
        $this->authorize('review', $model);
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        $updated = $this->templateService->approveReview(
            $model->id,
            (string) Auth::id(),
            fn (Template $template) => $this->attachCanCloneMeta($template, $request),
        );

        return new TemplateResource($updated);
    }

    /**
     * Publicación explícita de plantilla + snapshot (changelog obligatorio para v2+).
     */
    public function publish(PublishTemplateRequest $request, string $template): TemplateResource
    {
        $model = $this->templateService->findModelOrFail($template);
        $this->authorize('publish', $model);
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        $updated = $this->templateService->publishWithSnapshot(
            $model->id,
            $request->validated('changelog'),
            (string) Auth::id(),
            fn (Template $template) => $this->attachCanCloneMeta($template, $request),
        );

        return new TemplateResource($updated);
    }

    /**
     * Publicada → borrador (nueva versión de edición sobre la misma plantilla).
     */
    public function startNewVersion(StartNewTemplateRevisionRequest $request, string $template): TemplateResource|JsonResponse
    {
        $model = $request->resolveTemplate();
        $directAccess = $request->hasDirectTemplateAccess();

        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        $workingRevisionConflict = $this->templateService->resolveWorkingRevisionConflict($model);
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

        $viewerId = (string) $request->user()->getAuthIdentifier();
        $updated = $this->templateService->startNewRevisionCycle(
            $model->id,
            $viewerId,
            function (Template $template) use ($request, $viewerId): void {
                $this->attachCanCloneMeta($template, $request);
                $this->applyEmbeddedTeamToTemplate($template, $viewerId);
            },
        );

        return new TemplateResource($updated);
    }

    /**
     * Descarta una versión no publicada en curso y restaura la última publicación.
     */
    public function destroyVersion(Request $request, string $template, string $version): TemplateResource
    {
        $model = $this->templateService->findModelOrFail($template);
        $this->authorize('discard', $model);
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        $viewerId = (string) $request->user()->getAuthIdentifier();
        $updated = $this->templateService->destroyVersion(
            $model->id,
            $version,
            $viewerId,
            function (Template $template) use ($request, $viewerId): void {
                $this->attachCanCloneMeta($template, $request);
                $this->applyEmbeddedTeamToTemplate($template, $viewerId);
            },
        );

        return new TemplateResource($updated);
    }
}
