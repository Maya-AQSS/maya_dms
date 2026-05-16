<?php
declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Documents\CreateDocumentSnapshotDto;

interface SnapshotServiceInterface
{
    /**
     * Inserta un snapshot inmutable del documento (append-only).
     *
     * Para {@see CreateDocumentSnapshotDto::$triggerEvent} «published», crea antes la fila en
     * {@see \App\Models\EntityVersion} y enlaza {@see \App\Models\DocumentVersion::$entity_version_id}.
     */
    public function createDocumentSnapshot(CreateDocumentSnapshotDto $dto): void;
}
