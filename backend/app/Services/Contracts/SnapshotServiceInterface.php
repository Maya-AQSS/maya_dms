<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Documents\CreateDocumentSnapshotDto;
use App\Models\DocumentVersion;
use App\Models\EntityVersion;

interface SnapshotServiceInterface
{
    /**
     * Inserta un snapshot inmutable del documento (append-only).
     *
     * Para {@see CreateDocumentSnapshotDto::$triggerEvent} «published», crea antes la fila en
     * {@see EntityVersion} y enlaza {@see DocumentVersion::$entity_version_id}.
     */
    public function createDocumentSnapshot(CreateDocumentSnapshotDto $dto): void;
}
