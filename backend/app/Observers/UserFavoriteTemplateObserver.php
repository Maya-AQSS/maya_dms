<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\UserFavoriteTemplate;
use Illuminate\Database\Eloquent\Model;
use Maya\Messaging\Observers\AbstractAuditableModelObserver;

/**
 * Observer CRUD para {@see UserFavoriteTemplate}. Publica created/updated/deleted al
 * exchange `maya.audit` vía el patrón canónico
 * `AbstractAuditableModelObserver` shared.
 */
final class UserFavoriteTemplateObserver extends AbstractAuditableModelObserver
{
    protected function auditEntityType(): string
    {
        return 'user_favorite_template';
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

    public function created(UserFavoriteTemplate $model): void
    {
        $this->auditAfterCreate('created', $model);
    }

    public function updated(UserFavoriteTemplate $model): void
    {
        $this->auditAfterUpdate('updated', $model);
    }

    public function deleted(UserFavoriteTemplate $model): void
    {
        $this->auditAfterDelete('deleted', $model);
    }
}
