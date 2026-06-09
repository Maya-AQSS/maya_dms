<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DocumentReviewsSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('document_reviews')) {
            return;
        }

        $filePath = database_path('data/document_reviews_mock.php');
        if (! is_file($filePath)) {
            return;
        }

        /** @var mixed $loaded */
        $loaded = require $filePath;
        if (! is_array($loaded) || $loaded === []) {
            return;
        }

        $now = Carbon::now();

        $rows = array_map(static function (array $row) use ($now): array {
            $row['created_at'] ??= $now;
            $row['updated_at'] ??= $now;

            return $row;
        }, $loaded);

        DB::table('document_reviews')->insertOrIgnore($rows);
    }
}
