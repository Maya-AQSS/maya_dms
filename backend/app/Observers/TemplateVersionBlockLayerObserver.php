<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\TemplateVersionBlockLayer;
use Illuminate\Database\Eloquent\Model;
use Maya\Messaging\Observers\AbstractAuditableModelObserver;

/**
 * Observer CRUD para {@see TemplateVersionBlockLayer}. Publica created/updated/deleted al
 * exchange `maya.audit` vía el patrón canónico
 * `AbstractAuditableModelObserver` shared.
 */
final class TemplateVersionBlockLayerObserver extends AbstractAuditableModelObserver
{
    protected function auditEntityType(): string
    {
        return 'template_version_block_layer';
    }

    protected function auditEntityId(Model $model): string
    {
        $versionId = (string) ($model->getAttribute('entity_version_id') ?? '');
        $blockId = (string) ($model->getAttribute('template_block_id') ?? '');

        return "{$versionId}:{$blockId}";
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

    protected function auditSnapshot(Model $model): ?array
    {
        $snapshot = $model->getAttributes();
        $snapshot['_context'] = $this->buildContext($model);

        return $snapshot;
    }

    /**
     * @return array{0: ?array<string, mixed>, 1: ?array<string, mixed>}
     */
    protected function auditUpdateDiff(Model $model): array
    {
        [$previous, $new] = parent::auditUpdateDiff($model);

        if ($new !== null) {
            $new['_context'] = $this->buildContext($model, true);
        }

        return [$previous, $new];
    }

    public function created(TemplateVersionBlockLayer $model): void
    {
        $this->auditAfterCreate('created', $model);
    }

    public function updated(TemplateVersionBlockLayer $model): void
    {
        $this->auditAfterUpdate('updated', $model);
    }

    public function deleted(TemplateVersionBlockLayer $model): void
    {
        $this->auditAfterDelete('deleted', $model);
    }

    /** @return array<string, mixed> */
    private function buildContext(Model $model, bool $isUpdate = false): array
    {
        $payload = $model->override_payload;
        $title = is_array($payload) ? ($payload['title'] ?? null) : null;
        $removed = (bool) $model->getAttribute('removed');

        if ($isUpdate) {
            $key = 'audit.template_version_block_layer.updated';
        } else {
            $key = $removed
                ? 'audit.template_version_block_layer.removed'
                : 'audit.template_version_block_layer.included';
        }

        return array_filter([
            'description' => trans($key, [
                'block_title' => $title ?: trans('audit.unnamed', [], 'es'),
            ], 'es'),
            'block_title' => $title,
            'block_id' => $model->getAttribute('template_block_id'),
            'version_id' => $model->getAttribute('entity_version_id'),
        ], static fn ($v): bool => $v !== null && $v !== '');
    }
}
