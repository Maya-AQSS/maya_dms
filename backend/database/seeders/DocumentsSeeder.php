<?php

namespace Database\Seeders;

use App\Repositories\Contracts\DocumentRepositoryInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DocumentsSeeder extends Seeder
{
    private const DEFAULT_PROCESS_ID = '33333333-3333-3333-3333-333333333301';

    public function run(): void
    {
        if (! Schema::hasTable('documents')) {
            return;
        }

        $rows = $this->mockRows();
        if ($rows === []) {
            return;
        }

        $repo = app(DocumentRepositoryInterface::class);

        foreach ($rows as $row) {
            $row['process_id'] ??= self::DEFAULT_PROCESS_ID;
            $id = $row['id'] ?? null;
            if (is_string($id) && $id !== '' && DB::table('documents')->where('id', $id)->exists()) {
                continue;
            }

            $payload = array_diff_key($row, array_flip(['created_at', 'updated_at', 'deleted_at']));
            $repo->createDocumentWithBlocks($payload, []);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mockRows(): array
    {
        $filePath = database_path('data/documents_mock.php');

        if (! is_file($filePath)) {
            return [];
        }

        $rows = require $filePath;

        return is_array($rows) ? $rows : [];
    }
}
