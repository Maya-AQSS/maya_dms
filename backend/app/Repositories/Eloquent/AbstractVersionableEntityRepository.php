<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Base class for versionable entity repositories (Document, Template).
 *
 * Centralises the subset of methods that are structurally identical across
 * Document and Template domains. Domain-specific logic (model classes, FK
 * column names, error messages) is resolved via small abstract helpers.
 *
 * Methods deliberately NOT unified here:
 *  - normalizeHeadSnapshotUpdates(): private and differs per domain (different keys).
 *  - markFavorite/unmarkFavorite: live in UserFavoriteRepository, not in these repos.
 */
abstract class AbstractVersionableEntityRepository
{
    // ─── Abstract helpers ─────────────────────────────────────────────────────

    /**
     * Locate the entity by its primary key or throw ModelNotFoundException.
     * Implemented by each concrete repo with the correct Model and eager-loads.
     * Declared public so that concrete signatures (returning a narrower Model subtype)
     * are covariant-compatible and visible from the repository interface.
     */
    abstract public function findOrFail(string $id): Model;

    /**
     * Return the Eloquent model class used to query pending review stages.
     * E.g. DocumentReview::class or TemplateReviewer::class.
     *
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    abstract protected function pendingReviewModelClass(): string;

    /**
     * Return the FK column that scopes pending review rows to this entity.
     * E.g. 'document_id' or 'template_id'.
     */
    abstract protected function pendingReviewForeignKey(): string;

    // ─── Unified implementations ──────────────────────────────────────────────

    /**
     * Ejecuta una operación dentro de transacción.
     */
    public function transaction(callable $callback): mixed
    {
        return DB::transaction($callback);
    }

    /**
     * Persiste el changelog de envío a validación en la versión de trabajo (head).
     */
    public function updateHeadVersionChangelog(string $entityId, string $changelog): void
    {
        $entity = $this->findOrFail($entityId);
        $entity->loadMissing('headVersion');

        if ($entity->headVersion === null) {
            throw new RuntimeException('Entidad sin versión cabezal en entity_versions.');
        }

        $entity->headVersion->changelog = $changelog;
        $entity->headVersion->save();
    }

    /**
     * Elimina el changelog de envío de la versión de trabajo (head).
     */
    public function clearHeadVersionChangelog(string $entityId): void
    {
        $entity = $this->findOrFail($entityId);
        $entity->loadMissing('headVersion');

        if ($entity->headVersion === null) {
            return;
        }

        $entity->headVersion->changelog = null;
        $entity->headVersion->save();
    }

    /**
     * Etapa mínima entre revisiones con status pending para esta entidad, o null si no hay ninguna.
     */
    protected function minPendingReviewStage(string $entityId): ?int
    {
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $modelClass */
        $modelClass = $this->pendingReviewModelClass();

        $min = $modelClass::query()
            ->where($this->pendingReviewForeignKey(), $entityId)
            ->where('status', 'pending')
            ->min('stage');

        return $min !== null ? (int) $min : null;
    }
}
