<?php

namespace App\Repositories\Contracts;

interface DocumentRepositoryInterface
{
    /**
     * Indica si el usuario es autor (owner_id / created_by) o revisor asignado
     * del documento. Usado para control de acceso al historial de auditoría.
     */
    public function isAuthorOrReviewer(string $documentId, string $userId): bool;
}
