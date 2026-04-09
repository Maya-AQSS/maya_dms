<?php

namespace App\Services;

use App\Events\TemplateStateChanged;
use App\Models\Template;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Services\Contracts\TemplateServiceInterface;

class TemplateService implements TemplateServiceInterface
{
    public function __construct(
        private readonly TemplateRepositoryInterface $templateRepository,
    ) {}

    public function findOrFail(string $id): Template
    {
        return $this->templateRepository->findOrFail($id);
    }

    /**
     * Transiciona la plantilla a un nuevo estado y emite el evento de dominio TemplateStateChanged.
     */
    public function transition(string $templateId, string $newStatus, string $actorId): Template
    {
        $template  = $this->templateRepository->findOrFail($templateId);
        $oldStatus = $template->status;

        $template->update(['status' => $newStatus]);

        event(new TemplateStateChanged(
            template:  $template,
            oldStatus: $oldStatus,
            newStatus: $newStatus,
            actorId:   $actorId,
        ));

        return $template;
    }
}
