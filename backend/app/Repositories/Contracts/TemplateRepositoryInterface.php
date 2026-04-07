<?php

namespace App\Repositories\Contracts;

interface TemplateRepositoryInterface
{
    /**
     * Indica si el usuario es creador o revisor asignado de la plantilla.
     * Usado para control de acceso al historial de auditoría.
     */
    public function isCreatorOrReviewer(string $templateId, string $userId): bool;
}
