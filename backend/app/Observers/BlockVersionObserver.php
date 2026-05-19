<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\BlockVersion;
use Illuminate\Database\Eloquent\Model;
use Maya\Messaging\Observers\AbstractAuditableModelObserver;

/**
 * Observer CRUD para {@see BlockVersion}. Publica created/updated/deleted al
 * exchange `maya.audit` vía el patrón canónico
 * `AbstractAuditableModelObserver` shared.
 */
final class BlockVersionObserver extends AbstractAuditableModelObserver
{
    protected function auditEntityType(): string
    {
        return 'block_version';
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

    public function created(BlockVersion $model): void
    {
        $this->auditAfterCreate('created', $model);
    }

    public function updated(BlockVersion $model): void
    {
        $this->auditAfterUpdate('updated', $model);
    }

    public function deleted(BlockVersion $model): void
    {
        $this->auditAfterDelete('deleted', $model);
    }
}
