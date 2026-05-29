<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Document;
use App\Models\JwtUser;
use App\Models\Template;

/**
 * Permisos transversales de bloques (plantilla, documento y snapshots de versión).
 *
 * - `block.index` / `block.show`: compañero global (cualquier mutación plantilla o documento).
 * - `block.create` / `block.update` / `block.delete` en plantilla: slug `block.*` +
 *   {@see TemplatePolicy::update} sobre el padre (p. ej. creador en borrador personal sin
 *   `template.create`/`template.update`).
 * - Listado/detalle en plantilla: slug `block.index`/`block.show` +
 *   {@see TemplatePolicy::view} sobre el padre (sin compañero global de catálogo).
 * - `block.update` / `block.delete` en documento: compañero de documento +
 *   {@see DocumentPolicy::update} (quien edita el borrador, p. ej. titular o share `edit`).
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
        if (! $user->hasPermission('block.index')) {
            return false;
        }

        return (new TemplatePolicy)->view($user, $template);
    }

    /**
     * GET /blocks/{block} (bloque de plantilla).
     */
    public function showForTemplate(JwtUser $user, Template $template): bool
    {
        if (! $user->hasPermission('block.show')) {
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

    /**
     * POST /templates/{template}/blocks
     */
    public function createForTemplate(JwtUser $user, Template $template): bool
    {
        if (! $user->hasPermission('block.create')) {
            return false;
        }

        return (new TemplatePolicy)->update($user, $template);
    }

    /**
     * PUT /blocks/{block}, PATCH reorder, PUT blocks/bulk (plantilla).
     */
    public function updateForTemplate(JwtUser $user, Template $template): bool
    {
        if (! $user->hasPermission('block.update')) {
            return false;
        }

        return (new TemplatePolicy)->update($user, $template);
    }

    /**
     * DELETE /blocks/{block} (plantilla).
     */
    public function deleteForTemplate(JwtUser $user, Template $template): bool
    {
        if (! $user->hasPermission('block.delete')) {
            return false;
        }

        return (new TemplatePolicy)->update($user, $template);
    }

    /**
     * PUT /documents/{document}/blocks/{block}
     */
    public function updateForDocument(JwtUser $user, Document $document): bool
    {
        if (! $user->hasPermission('block.update') || ! $this->hasDocumentCompanionSlug($user)) {
            return false;
        }

        return (new DocumentPolicy)->update($user, $document);
    }

    /**
     * DELETE /documents/{document}/blocks/{block} (bloque opcional en borrador).
     *
     * Misma línea que editar contenido: titular, colaborador con share `edit`, etc.
     */
    public function deleteForDocument(JwtUser $user, Document $document): bool
    {
        if (! $user->hasPermission('block.delete') || ! $this->hasDocumentCompanionSlug($user)) {
            return false;
        }

        return (new DocumentPolicy)->update($user, $document);
    }

    private function hasCompanionMutationSlug(JwtUser $user): bool
    {
        return $this->hasTemplateCompanionSlug($user) || $this->hasDocumentCompanionSlug($user);
    }

    private function hasTemplateCompanionSlug(JwtUser $user): bool
    {
        return $user->hasPermission('template.create')
            || $user->hasPermission('template.update');
    }

    private function hasDocumentCompanionSlug(JwtUser $user): bool
    {
        return $user->hasPermission('document.create')
            || $user->hasPermission('document.update');
    }
}
