<?php

namespace App\Services;

use App\DTOs\Templates\SyncUsersDto;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TemplateReviewerAssignmentService
{
    public function __construct(
        private readonly TemplateRepositoryInterface $templateRepository,
    ) {}

    /**
     * Sincroniza los revisores de la plantilla normativa.
     *
     * En modo secuencial, si `review_stages` está definido y es mayor que cero,
     * el número de revisores no puede superar ese límite: cada revisor ocupa un stage propio.
     */
    public function syncReviewers(string $templateId, SyncUsersDto $dto): void
    {
        DB::transaction(function () use ($templateId, $dto): void {
            $template = $this->templateRepository->findOrFail($templateId);

            $uniqueUserIds = array_values(array_unique($dto->userIds));

            if (count($uniqueUserIds) !== count($dto->userIds)) {
                throw ValidationException::withMessages([
                    'user_ids' => ['La lista de revisores contiene IDs de usuario duplicados.'],
                ]);
            }

            if (
                $template->review_mode === 'sequential'
                && $template->review_stages > 0
                && count($uniqueUserIds) > $template->review_stages
            ) {
                throw ValidationException::withMessages([
                    'user_ids' => [
                        "La plantilla en modo secuencial admite un máximo de {$template->review_stages} revisor(es).",
                    ],
                ]);
            }

            // TemplateReviewer usa SoftDeletes; forceDelete elimina filas físicamente
            // para no violar la restricción única (template_id, user_id) al reinsertar.
            $template->reviewers()->withTrashed()->forceDelete();

            foreach ($uniqueUserIds as $index => $userId) {
                $template->reviewers()->create([
                    'user_id' => $userId,
                    'stage'   => $index + 1,
                ]);
            }
        });
    }

    /**
     * Sincroniza el pool de posibles revisores de documentos generados desde la plantilla.
     */
    public function syncDocumentReviewers(string $templateId, SyncUsersDto $dto): void
    {
        DB::transaction(function () use ($templateId, $dto): void {
            $template = $this->templateRepository->findOrFail($templateId);

            $uniqueUserIds = array_values(array_unique($dto->userIds));

            if (count($uniqueUserIds) !== count($dto->userIds)) {
                throw ValidationException::withMessages([
                    'user_ids' => ['La lista de validadores de documento contiene IDs de usuario duplicados.'],
                ]);
            }

            // TemplateDocumentReviewer no usa SoftDeletes: delete() es borrado físico.
            // A diferencia de TemplateReviewer (que sí usa SoftDeletes y requiere forceDelete),
            // aquí no hay riesgo de violar la constraint única al reinsertar.
            $template->documentReviewers()->delete();

            foreach ($uniqueUserIds as $userId) {
                $template->documentReviewers()->create([
                    'user_id' => $userId,
                ]);
            }
        });
    }

}
