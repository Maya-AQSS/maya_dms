<?php

namespace App\Services\Contracts;

use App\DTOs\Documents\CreateDocumentSnapshotDto;

interface SnapshotServiceInterface
{
    /**
     * Inserta un snapshot inmutable del documento (append-only).
     */
    public function createDocumentSnapshot(CreateDocumentSnapshotDto $dto): void;
}
