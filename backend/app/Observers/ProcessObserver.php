<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Process;
use Illuminate\Database\Eloquent\Model;
use Maya\Messaging\Observers\AbstractAuditableModelObserver;

/**
 * Observer CRUD para {@see Process}. Publica created/updated/deleted al
 * exchange `maya.audit` vía el patrón canónico
 * `AbstractAuditableModelObserver` shared.
 */
final class ProcessObserver extends AbstractAuditableModelObserver
{
    protected function auditEntityType(): string
    {
        return 'process';
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

    public function created(Process $model): void
    {
        $this->auditAfterCreate('created', $model);
    }

    public function updated(Process $model): void
    {
        $this->auditAfterUpdate('updated', $model);
    }

    public function deleted(Process $model): void
    {
        $this->auditAfterDelete('deleted', $model);
    }
}
