<?php

namespace App\Services;

use App\Events\TemplateStateChanged;
use App\Models\Template;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Repositories\Contracts\TemplateVersionRepositoryInterface;
use Illuminate\Validation\ValidationException;

class TemplatePublishingService
{
    public function __construct(
        private readonly TemplateRepositoryInterface $templateRepository,
        private readonly TemplateVersionRepositoryInterface $templateVersionRepository,
    ) {}

    /**
     * Publica la plantilla con un snapshot y emite el evento de dominio TemplatePublished.
     */
    public function publishWithSnapshot(string $templateId, ?string $changelog, string $actorId): Template
    {
        return $this->templateRepository->transaction(function () use ($templateId, $changelog, $actorId) {
            $template = $this->templateRepository->findOrFailForUpdate($templateId);

            if (! in_array($template->status, ['draft', 'in_review'], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Solo se puede publicar una plantilla en borrador o en revisión.'],
                ]);
            }

            $template->load(['blocks' => fn ($q) => $q->orderBy('sort_order')]);

            $blocksSnapshot = $template->blocks->map(fn ($b) => [
                'id' => $b->id,
                'title' => $b->title,
                'description' => $b->description,
                'default_content' => $b->default_content,
                'block_state' => $b->block_state,
                'sort_order' => $b->sort_order,
            ])->values()->all();

            $next = $this->templateVersionRepository->nextVersionNumber($templateId);
            $trimmedChangelog = is_string($changelog) ? trim($changelog) : '';
            $resolvedChangelog = $trimmedChangelog;

            if ($resolvedChangelog === '') {
                if ($next === 1) {
                    $resolvedChangelog = 'Versión inicial';
                } else {
                    throw ValidationException::withMessages([
                        'changelog' => ['El changelog es obligatorio al publicar una plantilla.'],
                    ]);
                }
            }

            $this->templateVersionRepository->createSnapshot(
                $templateId,
                $next,
                $blocksSnapshot,
                $resolvedChangelog,
                $actorId,
            );

            $oldStatus = $template->status;
            $updated = $this->templateRepository->update($template, [
                'status' => 'published',
                'version' => $next,
            ]);

            event(new TemplateStateChanged(
                template: $updated,
                oldStatus: $oldStatus,
                newStatus: 'published',
                actorId: $actorId,
            ));

            return $updated;
        });
    }
}
