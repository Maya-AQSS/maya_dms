<?php

namespace App\Repositories\Contracts;

use App\Models\Template;

interface TemplateRepositoryInterface
{
    /**
     * Localiza una plantilla por su ID o lanza una excepción.
     */
    public function findOrFail(string $id): Template;

    /**
     * Indica si el usuario es creador o revisor asignado de la plantilla.
     * Usado para control de acceso al historial de auditoría.
     */
    public function isCreatorOrReviewer(string $templateId, string $userId): bool;
}
