<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\Template;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class EntityVersionsSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('entity_versions')) {
            return;
        }

        $rows = $this->mockRows();
        if ($rows === []) {
            return;
        }

        $now = Carbon::now();

        $normalizedRows = [];
        foreach ($rows as $row) {
            $type = (string) ($row['versionable_type'] ?? '');
            $id = (string) ($row['versionable_id'] ?? '');
            if ($type === '' || $id === '') {
                continue;
            }

            if (! $this->versionableExists($type, $id)) {
                continue;
            }

            $row['change_set'] = $this->asJsonString($row['change_set'] ?? null);
            $row['snapshot_data'] = $this->asJsonString($row['snapshot_data'] ?? null);
            $row['is_snapshot_immutable'] = (bool) ($row['is_snapshot_immutable'] ?? false);
            $row['status'] ??= 'draft';
            $row['created_at'] ??= $now;
            $row['updated_at'] ??= $now;

            $normalizedRows[] = $row;
        }

        if ($normalizedRows !== []) {
            DB::table('entity_versions')->insertOrIgnore($normalizedRows);
        }

        $this->backfillMissingDocumentPublishedSnapshots();
    }

    /**
     * Seeded documents created with status='published' via createDocumentWithBlocks only
     * get a HEAD entity_version (version_number=0). The history API filters version_number>0,
     * so no published snapshot appears in the panel. This method creates the missing v1 rows
     * by copying each HEAD's snapshot_data.
     */
    private function backfillMissingDocumentPublishedSnapshots(): void
    {
        if (! Schema::hasTable('entity_versions')) {
            return;
        }

        $now = Carbon::now();

        // Find HEAD entity_versions (v0, published) for documents that have no v>=1 snapshot.
        $heads = DB::table('entity_versions as head')
            ->where('head.versionable_type', Document::class)
            ->where('head.version_number', 0)
            ->where('head.status', 'published')
            ->whereNotExists(function ($sub) {
                $sub->from('entity_versions as snap')
                    ->whereColumn('snap.versionable_id', 'head.versionable_id')
                    ->where('snap.versionable_type', Document::class)
                    ->where('snap.version_number', '>', 0)
                    ->where('snap.status', 'published');
            })
            ->get(['head.id', 'head.versionable_id', 'head.created_by', 'head.published_by', 'head.published_at', 'head.snapshot_data', 'head.created_at']);

        foreach ($heads as $head) {
            $publishedBy = $head->published_by ?? $head->created_by;
            DB::table('entity_versions')->insertOrIgnore([[
                'id' => (string) Str::uuid(),
                'versionable_type' => Document::class,
                'versionable_id' => $head->versionable_id,
                'version_number' => 1,
                'base_version_id' => null,
                'change_set' => null,
                'status' => 'published',
                'is_snapshot_immutable' => true,
                'created_by' => $head->created_by,
                'published_by' => $publishedBy,
                'published_at' => $head->published_at ?? $now,
                'changelog' => 'Publicación inicial',
                'snapshot_data' => $head->snapshot_data,
                'created_at' => $head->created_at ?? $now,
                'updated_at' => $now,
            ]]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mockRows(): array
    {
        $filePath = database_path('data/entity_versions_mock.php');
        if (! is_file($filePath)) {
            return [];
        }

        $rows = require $filePath;

        return is_array($rows) ? $rows : [];
    }

    private function versionableExists(string $type, string $id): bool
    {
        return match ($type) {
            Template::class => DB::table('templates')->where('id', $id)->exists(),
            Document::class => DB::table('documents')->where('id', $id)->exists(),
            default => false,
        };
    }

    private function asJsonString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            return $value;
        }

        return json_encode($value);
    }
}
