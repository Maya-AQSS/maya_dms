<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TemplateDocumentReviewersSeeder extends Seeder
{
    public function run(): void
    {
        $data = $this->mockData();

        if ($data === [] || ! Schema::hasTable('template_document_reviewers')) {
            return;
        }

        $reviewers = $data['template_document_reviewers'] ?? [];
        if ($reviewers === []) {
            return;
        }

        $now = Carbon::now();

        $rows = [];
        foreach ($reviewers as $index => $row) {
            $row['created_at'] ??= $now;
            $row['updated_at'] ??= $now;
            $row['stage'] ??= $index + 1;
            $rows[] = $row;
        }

        DB::table('template_document_reviewers')->insertOrIgnore($rows);
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
