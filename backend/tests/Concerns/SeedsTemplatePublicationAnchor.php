<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Template;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * En tests, {@code documents.template_version_id} referencia {@see entity_versions} (publicación canónica).
 */
trait SeedsTemplatePublicationAnchor
{
    /**
     * Inserta una fila publicada en {@see entity_versions}.
     *
     * @param  list<array<string, mixed>>  $snapshotBlocks  Filas de bloque para snapshot_data.blocks
     * @param  array<string, mixed>|null  $extraSnapshot   Claves extra en snapshot_data (p. ej. reviewers)
     * @return array{entity_version_id: string}
     */
    protected function seedCanonicalPublicationForTemplate(
        string $templateId,
        int $versionNumber,
        string $publishedBy,
        array $snapshotBlocks,
        ?array $extraSnapshot = null,
    ): array {
        $entityVersionId = (string) Str::uuid();
        $now = now();

        $snapshot = ['blocks' => $snapshotBlocks];
        if ($extraSnapshot !== null && $extraSnapshot !== []) {
            $snapshot = array_merge($snapshot, $extraSnapshot);
        }

        DB::table('entity_versions')->insert([
            'id' => $entityVersionId,
            'versionable_type' => Template::class,
            'versionable_id' => $templateId,
            'version_number' => $versionNumber,
            'base_version_id' => null,
            'change_set' => null,
            'status' => 'published',
            'created_by' => $publishedBy,
            'published_by' => $publishedBy,
            'published_at' => $now,
            'changelog' => 'v'.$versionNumber,
            'snapshot_data' => json_encode($snapshot),
            'is_snapshot_immutable' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['entity_version_id' => $entityVersionId];
    }
}
