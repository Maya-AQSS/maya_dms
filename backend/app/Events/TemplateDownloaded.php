<?php

declare(strict_types=1);

namespace App\Events;

use App\Http\Controllers\Api\TemplateVersionController;
use Illuminate\Foundation\Events\Dispatchable;
use Maya\Messaging\Contracts\AuditableEvent;
use Maya\Messaging\Listeners\RecordAuditableEvent;
use Maya\Messaging\Support\MessagingConfig;

/**
 * Hecho de negocio: un usuario ha descargado el PDF del snapshot de una
 * versión publicada de una plantilla. Disparado desde
 * {@see TemplateVersionController}; el wildcard
 * del package shared-messaging ({@see RecordAuditableEvent})
 * publica al exchange `maya.audit` tras commit.
 */
class TemplateDownloaded implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly string $templateId,
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
            'entityType' => 'template',
            'entityId' => $this->templateId,
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
