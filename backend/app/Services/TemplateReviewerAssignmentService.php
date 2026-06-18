<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Templates\SyncUsersDto;
use App\Models\Template;
use App\Repositories\Contracts\ResolvedPermissionReaderInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use Illuminate\Validation\ValidationException;

/**
 * Sincroniza revisores de plantilla y pool de validadores de documento.
 *
 * Los candidatos se validan solo por permiso de revisión; el ámbito académico
 * de la plantilla no restringe quién puede ser validador.
 *
 * Las notificaciones de trabajo pendiente se envían solo al enviar a revisión
 * ({@see TemplateReviewService::submitForReview}, {@see DocumentService::submitToReview}),
 * no al asignar usuarios en borrador.
 */
class TemplateReviewerAssignmentService
{
    public function __construct(
        private readonly TemplateRepositoryInterface $templateRepository,
        private readonly ResolvedPermissionReaderInterface $resolvedPermissions,
    ) {}

    /**
     * Sincroniza los revisores de la plantilla normativa.
     *
     * En modo secuencial, si `review_stages` está definido y es mayor que cero,
     * el número de revisores no puede superar ese límite: cada revisor ocupa un stage propio.
     */
    public function syncReviewers(string $templateId, SyncUsersDto $dto): void
    {
        $this->templateRepository->transaction(function () use ($templateId, $dto): void {
            $template = $this->templateRepository->findOrFail($templateId);

            $uniqueUserIds = array_values(array_unique($dto->userIds));

            if (count($uniqueUserIds) !== count($dto->userIds)) {
                throw ValidationException::withMessages([
                    'user_ids' => [__('validation.reviewers.duplicate_ids')],
                ]);
            }

            if (
                $template->review_mode === 'sequential'
                && $template->review_stages > 0
                && count($uniqueUserIds) > $template->review_stages
            ) {
                throw ValidationException::withMessages([
                    'user_ids' => [
                        __('validation.reviewers.sequential_max', ['max' => $template->review_stages]),
                    ],
                ]);
            }

            $this->assertUsersHavePermission($uniqueUserIds, 'template.review', 'user_ids');

            // TemplateReviewer usa SoftDeletes; forceDelete elimina filas físicamente
            // para no violar la restricción única (template_id, user_id) al reinsertar.
            // Usamos repository para encapsular esta lógica.
            $reviewerData = array_map(
                fn (string $userId, int $index) => ['user_id' => $userId, 'stage' => $index + 1],
                $uniqueUserIds,
                array_keys($uniqueUserIds)
            );
            $this->templateRepository->syncTemplateReviewers($templateId, $reviewerData);
        });
    }

    /**
     * Sincroniza el pool de posibles revisores de documentos generados desde la plantilla.
     */
    public function syncDocumentReviewers(string $templateId, SyncUsersDto $dto): void
    {
        $this->templateRepository->transaction(function () use ($templateId, $dto): void {
            $this->templateRepository->findOrFail($templateId);

            $uniqueUserIds = array_values(array_unique($dto->userIds));

            if (count($uniqueUserIds) !== count($dto->userIds)) {
                throw ValidationException::withMessages([
                    'user_ids' => [__('validation.reviewers.duplicate_document_ids')],
                ]);
            }

            $this->assertUsersHavePermission($uniqueUserIds, 'document.review', 'user_ids');

            // TemplateDocumentReviewer no usa SoftDeletes: delete() es borrado físico.
            // Usamos repository para encapsular esta lógica.
            $reviewerData = array_map(
                fn (string $userId, int $index) => ['user_id' => $userId, 'stage' => $index + 1],
                $uniqueUserIds,
                array_keys($uniqueUserIds)
            );
            $this->templateRepository->syncDocumentReviewers($templateId, $reviewerData);
        });
    }

    /**
     * Aserta que los usuarios tienen el permiso requerido.
     *
     * @param  list<string>  $userIds
     */
    private function assertUsersHavePermission(array $userIds, string $requiredPermission, string $field): void
    {
        $missingPermission = [];
        foreach ($userIds as $userId) {
            $slugs = $this->resolvedPermissions->findPermissionSlugsByUserId($userId);
            if (! in_array($requiredPermission, $slugs, true)) {
                $missingPermission[] = $userId;
            }
        }

        if ($missingPermission === []) {
            return;
        }

        throw ValidationException::withMessages([
            $field => [
                __('validation.reviewers.missing_permission', ['permission' => $requiredPermission]),
            ],
        ]);
    }
}
