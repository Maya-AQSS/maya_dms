<?php

declare(strict_types=1);

namespace App\DTOs\Versioning;

use App\Models\EntityVersion;

/**
 * Versión polimórfica ({@see EntityVersion}) para exposición API y consumo
 * entre Services sin acoplar al Model Eloquent. Las fechas van pre-formateadas
 * en ISO-8601 (mismo formato que emitían los Resources sobre el Model).
 */
final readonly class EntityVersionDto
{
    /**
     * @param  array<string, mixed>|null  $snapshotData
     */
    public function __construct(
        public string $id,
        public string $versionableType,
        public string $versionableId,
        public int $versionNumber,
        public ?string $baseVersionId,
        public string $status,
        public bool $isSnapshotImmutable,
        public ?array $snapshotData,
        public ?string $changelog,
        public ?string $createdBy,
        public ?string $publishedBy,
        public ?string $publishedAt,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    public static function fromModel(EntityVersion $v): self
    {
        return new self(
            id: (string) $v->id,
            versionableType: (string) $v->versionable_type,
            versionableId: (string) $v->versionable_id,
            versionNumber: (int) $v->version_number,
            baseVersionId: $v->base_version_id !== null ? (string) $v->base_version_id : null,
            status: (string) $v->status,
            isSnapshotImmutable: (bool) $v->is_snapshot_immutable,
            snapshotData: is_array($v->snapshot_data) ? $v->snapshot_data : null,
            changelog: $v->changelog !== null ? (string) $v->changelog : null,
            createdBy: $v->created_by !== null ? (string) $v->created_by : null,
            publishedBy: $v->published_by !== null ? (string) $v->published_by : null,
            publishedAt: $v->published_at?->toIso8601String(),
            createdAt: $v->created_at?->toIso8601String(),
            updatedAt: $v->updated_at?->toIso8601String(),
        );
    }
}
