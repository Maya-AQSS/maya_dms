<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Document;
use App\Models\JwtUser;
use App\Models\Template;

/**
 * Permisos transversales de bloques (plantilla, documento y snapshots de versión).
 *
 * - `block.index` / `block.show` exigen además al menos un slug de mutación de plantilla
 *   o documento (`template.create`, `template.update`, `document.create`, `document.update`).
 * - El acceso al padre (plantilla/documento) se valida en cada método contextual.
 */
class BlockPolicy
{
    /**
     * Listar bloques (catálogo `block.index` + mutación plantilla/documento).
     */
    public function viewAny(JwtUser $user): bool
    {
        return $user->hasPermission('block.index') && $this->hasCompanionMutationSlug($user);
    }

    /**
     * Ver detalle de un bloque (catálogo `block.show` + mutación plantilla/documento).
     */
    public function view(JwtUser $user): bool
    {
        return $user->hasPermission('block.show') && $this->hasCompanionMutationSlug($user);
    }

    /**
     * GET /templates/{template}/blocks
     */
    public function listForTemplate(JwtUser $user, Template $template): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return (new TemplatePolicy)->view($user, $template);
    }

    /**
     * GET /blocks/{block} (bloque de plantilla).
     */
    public function showForTemplate(JwtUser $user, Template $template): bool
    {
        if (! $this->view($user)) {
            return false;
        }

        return (new TemplatePolicy)->view($user, $template);
    }

    /**
     * GET /documents/{document}/blocks
     */
    public function listForDocument(JwtUser $user, Document $document): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return (new DocumentPolicy)->view($user, $document);
    }

    private function hasCompanionMutationSlug(JwtUser $user): bool
    {
        return $user->hasPermission('template.create')
            || $user->hasPermission('template.update')
            || $user->hasPermission('document.create')
            || $user->hasPermission('document.update');
    }
}
