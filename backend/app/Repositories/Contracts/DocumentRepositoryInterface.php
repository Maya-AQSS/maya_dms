<?php

namespace App\Repositories\Contracts;

use App\Models\Document;

interface DocumentRepositoryInterface
{
    /**
     * Localiza un documento por su ID o lanza ModelNotFoundException.
     */
    public function findOrFail(string $id): Document;

    /**
     * Indica si el usuario es autor (owner_id / created_by) o revisor asignado
     * del documento. Usado para control de acceso al historial de auditoría.
     */
    public function isAuthorOrReviewer(string $documentId, string $userId): bool;
}
