<?php

namespace App\Http\Controllers\Api;

use App\DTOs\Templates\TemplateDto;
use App\Http\Concerns\AttachesTemplateCanCloneMeta;
use App\Http\Concerns\ValidatesOptionalProcessContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Templates\PublishTemplateRequest;
use App\Http\Requests\Templates\StartNewTemplateRevisionRequest;
use App\Http\Resources\TemplateResource;
use App\Services\Contracts\ApiTeamEmbedServiceInterface;
use App\Services\Contracts\TemplateServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Transiciones de estado de Template: submitForReview, rejectReview,
 * approveReview, publish, startNewVersion, destroyVersion. Split de
 * {@see TemplateController} para cumplir B9.
 */
class TemplateStateController extends Controller
{
    use AttachesTemplateCanCloneMeta;
    use ValidatesOptionalProcessContext;

    public function __construct(
        private readonly TemplateServiceInterface $templateService,
        private readonly ApiTeamEmbedServiceInterface $apiTeamEmbedService,
    ) {}

    /**
     * Borrador → en revisión (autor o quien puede editar).
     */
    public function submitForReview(string $template): TemplateResource
    {
        $model = $this->templateService->findModelOrFail($template);
        $this->authorize('submitForReview', $model);
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        $updated = $this->templateService->submitForReview($model->id, (string) Auth::id());
        $updated->setAttribute('can_clone', Gate::forUser(Auth::user())->allows('clone', $updated));

        return new TemplateResource(TemplateDto::fromModel($updated));
    }

    /**
     * En revisión → borrador (revisor).
     */
    public function rejectReview(string $template): TemplateResource
    {
        $model = $this->templateService->findModelOrFail($template);
        $this->authorize('review', $model);
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        $updated = $this->templateService->rejectReview($model->id, (string) Auth::id());
        $updated->setAttribute('can_clone', Gate::forUser(Auth::user())->allows('clone', $updated));

        return new TemplateResource(TemplateDto::fromModel($updated));
    }

    /**
     * En revisión → aprobación del revisor activo.
     *
     * Si todos los revisores han aprobado, la plantilla se publica automáticamente.
     * En modo secuencial verifica que los stages anteriores estén aprobados.
     */
    public function approveReview(string $template): TemplateResource
    {
        $model = $this->templateService->findModelOrFail($template);
        $this->authorize('review', $model);
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        $updated = $this->templateService->approveReview($model->id, (string) Auth::id());
        $updated->setAttribute('can_clone', Gate::forUser(Auth::user())->allows('clone', $updated));

        return new TemplateResource(TemplateDto::fromModel($updated));
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
        );
        $this->attachCanCloneMeta($updated, $request);

        return new TemplateResource(TemplateDto::fromModel($updated));
    }

    /**
     * Publicada → borrador (nueva versión de edición sobre la misma plantilla).
     */
    public function startNewVersion(StartNewTemplateRevisionRequest $request, string $template): TemplateResource
    {
        $model = $this->templateService->findModelOrFail($template);
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        $updated = $this->templateService->startNewRevisionCycle(
            $model->id,
            (string) $request->user()->getAuthIdentifier(),
        );
        $this->attachCanCloneMeta($updated, $request);

        $this->apiTeamEmbedService->embedOnTemplate(
            $updated,
            (string) $request->user()->getAuthIdentifier(),
        );

        return new TemplateResource(TemplateDto::fromModel($updated));
    }

    /**
     * Descarta una versión no publicada en curso y restaura la última publicación.
     */
    public function destroyVersion(Request $request, string $template, string $version): TemplateResource
    {
        $model = $this->templateService->findModelOrFail($template);
        $this->authorize('update', $model);
        $this->assertOptionalProcessContextMatches((string) $model->process_id);

        $updated = $this->templateService->destroyVersion(
            $model->id,
            $version,
            (string) $request->user()->getAuthIdentifier(),
        );
        $this->attachCanCloneMeta($updated, $request);

        $this->apiTeamEmbedService->embedOnTemplate(
            $updated,
            (string) $request->user()->getAuthIdentifier(),
        );

        return new TemplateResource(TemplateDto::fromModel($updated));
    }
}
