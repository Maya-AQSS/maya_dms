<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\Template;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
