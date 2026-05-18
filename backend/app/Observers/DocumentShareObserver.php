<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\DocumentShare;
use Illuminate\Database\Eloquent\Model;
use Maya\Messaging\Observers\AbstractAuditableModelObserver;

/**
 * Observer CRUD para {@see DocumentShare}. Publica created/updated/deleted al
 * exchange `maya.audit` vía el patrón canónico
 * `AbstractAuditableModelObserver` shared.
 */
final class DocumentShareObserver extends AbstractAuditableModelObserver
{
    protected function auditEntityType(): string
    {
        return 'document_share';
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

    public function created(DocumentShare $model): void
    {
        $this->auditAfterCreate('created', $model);
    }

    public function updated(DocumentShare $model): void
    {
        $this->auditAfterUpdate('updated', $model);
    }

    public function deleted(DocumentShare $model): void
    {
        $this->auditAfterDelete('deleted', $model);
    }
}
