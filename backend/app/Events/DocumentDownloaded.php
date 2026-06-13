<?php

declare(strict_types=1);

namespace App\Events;

use App\Http\Controllers\Api\DocumentExportController;
use Illuminate\Foundation\Events\Dispatchable;
use Maya\Messaging\Contracts\AuditableEvent;
use Maya\Messaging\Listeners\RecordAuditableEvent;
use Maya\Messaging\Support\MessagingConfig;

/**
 * Hecho de negocio: un usuario ha descargado el PDF de un documento (el HEAD
 * publicado o el snapshot de una versión histórica). Disparado desde
 * {@see DocumentExportController}; el wildcard del
 * package shared-messaging ({@see RecordAuditableEvent})
 * publica al exchange `maya.audit` tras commit.
 */
class DocumentDownloaded implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly string $documentId,
        public readonly string $userId,
        public readonly string $format = 'pdf',
        public readonly ?string $versionId = null,
        public readonly ?int $versionNumber = null,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
    ) {}

    public function toAuditPayload(): array
    {
        return [
            'applicationSlug' => MessagingConfig::appSlug(),
            'entityType' => 'document',
            'entityId' => $this->documentId,
            'action' => 'downloaded',
            'userId' => $this->userId,
            'newValue' => array_filter([
                'format' => $this->format,
                'version_id' => $this->versionId,
                'version_number' => $this->versionNumber,
            ], static fn ($value) => $value !== null),
            'ipAddress' => $this->ipAddress,
            'userAgent' => $this->userAgent,
        ];
    }
}
