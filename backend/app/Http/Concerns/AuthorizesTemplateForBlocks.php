<?php

namespace App\Http\Concerns;

use App\Models\Template;
use App\Services\Contracts\TemplateServiceInterface;

/**
 * Resuelve el Template asociado a un bloque y autoriza la operación.
 * Compartido entre TemplateBlockController (CRUD) y TemplateBlockBulkController
 * (reorder / bulkUpdate) para evitar duplicación de helpers.
 */
trait AuthorizesTemplateForBlocks
{
    use ValidatesOptionalProcessContext;

    /**
     * Misma resolución que TemplateController::show: la política puede autorizar
     * ver una plantilla fuera del catálogo (p. ej. anclada a un documento visible);
     * el scope user_access no debe devolver 404 antes de la autorización.
     */
    protected function findTemplateOrFail(TemplateServiceInterface $templateService, string $templateId): Template
    {
        return $templateService->findOrFailWithoutCatalogScope($templateId);
    }

    protected function authorizeAndValidateTemplateContext(
        Template $template,
        string $ability,
        bool $checkProcessContext = true,
    ): void {
        $this->authorize($ability, $template);

        if ($checkProcessContext) {
            $this->assertOptionalProcessContextMatches((string) $template->process_id);
        }
    }
}
