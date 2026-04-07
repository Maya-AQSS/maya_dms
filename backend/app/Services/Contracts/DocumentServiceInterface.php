<?php

namespace App\Services\Contracts;

use App\Models\Document;

interface DocumentServiceInterface
{
    /**
     * Transiciona el documento a un nuevo estado y emite el evento de dominio DocumentStateChanged.
     */
    public function transition(string $documentId, string $newStatus, string $actorId): Document;
}
