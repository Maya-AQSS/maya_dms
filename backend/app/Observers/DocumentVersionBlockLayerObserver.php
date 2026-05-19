<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\DocumentVersionBlockLayer;
use Illuminate\Database\Eloquent\Model;
use Maya\Messaging\Observers\AbstractAuditableModelObserver;

/**
 * Observer CRUD para {@see DocumentVersionBlockLayer}. Publica created/updated/deleted al
 * exchange `maya.audit` vía el patrón canónico
 * `AbstractAuditableModelObserver` shared.
 */
final class DocumentVersionBlockLayerObserver extends AbstractAuditableModelObserver
{
    protected function auditEntityType(): string
    {
        return 'document_version_block_layer';
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

    public function created(DocumentVersionBlockLayer $model): void
    {
        $this->auditAfterCreate('created', $model);
    }

    public function updated(DocumentVersionBlockLayer $model): void
    {
        $this->auditAfterUpdate('updated', $model);
    }

    public function deleted(DocumentVersionBlockLayer $model): void
    {
        $this->auditAfterDelete('deleted', $model);
    }
}
