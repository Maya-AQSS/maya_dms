<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\DocumentVersion;
use Illuminate\Database\Eloquent\Model;
use Maya\Messaging\Observers\AbstractAuditableModelObserver;

/**
 * Observer CRUD para {@see DocumentVersion}. Publica created/updated/deleted al
 * exchange `maya.audit` vía el patrón canónico
 * `AbstractAuditableModelObserver` shared.
 */
final class DocumentVersionObserver extends AbstractAuditableModelObserver
{
    protected function auditEntityType(): string
    {
        return 'document_version';
    }

    /** @return list<string> */
    protected function auditTemporalKeys(): array
    {
        return ['created_at', 'updated_at'];
    }

    protected function resolveAuditUserId(Model $model): string
    {
        $panel = $this->resolvePanelActorUserId(null);

        return $panel ?? (string) ($model->getAttribute('user_id') ?? 'system');
    }

    public function created(DocumentVersion $model): void
    {
        $this->auditAfterCreate('created', $model);
    }

    public function updated(DocumentVersion $model): void
    {
        $this->auditAfterUpdate('updated', $model);
    }

    public function deleted(DocumentVersion $model): void
    {
        $this->auditAfterDelete('deleted', $model);
    }
}
