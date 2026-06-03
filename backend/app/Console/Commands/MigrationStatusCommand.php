<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Reports how many rows in each block-bearing table still hold a legacy
 * BlockNote backup. Useful to confirm a migration run cleared everything
 * before dropping the column.
 */
final class MigrationStatusCommand extends Command
{
    protected $signature = 'migration:status';

    protected $description = 'Show BlockNote → TipTap migration progress.';

    public function handle(): int
    {
        $tables = ['template_blocks', 'document_blocks', 'block_versions'];
        $rows = [];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                $rows[] = [$table, 'missing', '-', '-'];
                continue;
            }
            $hasLegacy = Schema::hasColumn($table, 'content_legacy_blocknote');
            $total = DB::table($table)->count();
            $withLegacy = $hasLegacy
                ? DB::table($table)->whereNotNull('content_legacy_blocknote')->count()
                : 0;
            $rows[] = [
                $table,
                $hasLegacy ? 'present' : 'dropped',
                $total,
                $hasLegacy ? $withLegacy : '-',
            ];
        }

        $this->table(
            ['Table', 'Legacy column', 'Total rows', 'Rows with backup'],
            $rows,
        );

        return self::SUCCESS;
    }
}
