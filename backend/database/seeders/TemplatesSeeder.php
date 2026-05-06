<?php

namespace Database\Seeders;

use App\Repositories\Contracts\TemplateRepositoryInterface;
use Illuminate\Database\Seeder;
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

        $repo = app(TemplateRepositoryInterface::class);

        foreach ($templates as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = isset($row['id']) ? (string) $row['id'] : null;
            if ($id !== null && DB::table('templates')->where('id', $id)->exists()) {
                continue;
            }

            $row['process_id'] ??= self::DEFAULT_PROCESS_ID;
            $row['status'] ??= 'draft';

            $repo->create($row);
        }
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
