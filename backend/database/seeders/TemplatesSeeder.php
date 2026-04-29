<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TemplatesSeeder extends Seeder
{
    private const DEFAULT_PROCESS_ID = '33333333-3333-3333-3333-333333333301';

    public function run(): void
    {
        $data = $this->mockData();

        if ($data === [] || ! Schema::hasTable('templates')) {
            return;
        }

        $templates = $data['templates'] ?? [];
        if ($templates === []) {
            return;
        }

        $now = Carbon::now();

        $rows = array_map(static function (array $row) use ($now): array {
            $row['process_id'] ??= self::DEFAULT_PROCESS_ID;
            $row['created_at'] ??= $now;
            $row['updated_at'] ??= $now;

            return $row;
        }, $templates);

        DB::table('templates')->upsert(
            $rows,
            ['id'],
            [
                'name',
                'description',
                'process_id',
                'visibility_level',
                'delivery_deadline',
                'study_id',
                'study_type_id',
                'module_id',
                'team_id',
                'created_by',
                'status',
                'version',
                'review_stages',
                'review_mode',
                'updated_at',
            ]
        );
    }

    /**
     * Lee datos mock desde database/data/templates_mock.php.
     *
     * @return array<string, mixed>
     */
    private function mockData(): array
    {
        $filePath = database_path('data/templates_mock.php');

        if (! is_file($filePath)) {
            return [];
        }

        $data = require $filePath;

        return is_array($data) ? $data : [];
    }
}
