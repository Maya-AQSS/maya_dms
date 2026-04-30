<?php

namespace App\Services;

use App\Repositories\Contracts\TemplateRepositoryInterface;
use Illuminate\Support\Facades\DB;

class TemplateReviewerAssignmentService
{
    public function __construct(
        private readonly TemplateRepositoryInterface $templateRepository,
    ) {}

    /**
     * Sincroniza los revisores de la plantilla normativa.
     *
     * @param  list<string>  $userIds
     */
    public function syncReviewers(string $templateId, array $userIds): void
    {
        DB::transaction(function () use ($templateId, $userIds): void {
            $template = $this->templateRepository->findOrFail($templateId);

            // TemplateReviewer usa SoftDeletes; forceDelete elimina filas físicamente
            // para no violar la restricción única (template_id, user_id) al reinsertar.
            $template->reviewers()->withTrashed()->forceDelete();

            foreach ($userIds as $index => $userId) {
                $template->reviewers()->create([
                    'user_id' => $userId,
                    'stage'   => $index + 1,
                ]);
            }
        });
    }

    /**
     * Sincroniza el pool de posibles revisores de documentos generados desde la plantilla.
     *
     * @param  list<string>  $userIds
     */
    public function syncDocumentReviewers(string $templateId, array $userIds): void
    {
        DB::transaction(function () use ($templateId, $userIds): void {
            $template = $this->templateRepository->findOrFail($templateId);

            $template->documentReviewers()->delete();

            foreach ($userIds as $userId) {
                $template->documentReviewers()->create([
                    'user_id' => $userId,
                ]);
            }
        });
    }

}
