<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\DocumentBlock;
use Illuminate\Database\Eloquent\Model;
use Maya\Messaging\Observers\AbstractAuditableModelObserver;

/**
 * Observer CRUD para {@see DocumentBlock}. Publica created/updated/deleted al
 * exchange `maya.audit` vía el patrón canónico
 * `AbstractAuditableModelObserver` shared.
 */
final class DocumentBlockObserver extends AbstractAuditableModelObserver
{
    protected function auditEntityType(): string
    {
        return 'document_block';
    }

    /** @return list<string> */
    protected function auditTemporalKeys(): array
    {
        return ['created_at', 'updated_at', 'deleted_at'];
    }

    protected function resolveAuditUserId(Model $model): string
    {
        $panel = $this->resolvePanelActorUserId(null);

        return $panel ?? (string) ($model->getAttribute('user_id') ?? 'system');
    }

    public function created(DocumentBlock $model): void
    {
        $this->auditAfterCreate('created', $model);
    }

    public function updated(DocumentBlock $model): void
    {
        $this->auditAfterUpdate('updated', $model);
    }

    public function deleted(DocumentBlock $model): void
    {
        $this->auditAfterDelete('deleted', $model);
    }
}
