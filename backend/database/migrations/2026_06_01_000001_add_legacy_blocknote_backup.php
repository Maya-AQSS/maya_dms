<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the `content_legacy_blocknote` JSON column to the three block-bearing
 * tables so the BlockNote → TipTap migration command can copy the original
 * payload before transforming. The column is dropped by the migration command
 * itself once HTML parity is verified across all rows (see
 * `blocknote:migrate-to-tiptap`).
 *
 * NOT a destructive change — the column is nullable and never read at app
 * runtime; only the migration command and rollback path touch it.
 */
return new class extends Migration
{
    private const TABLES = ['template_blocks', 'document_blocks', 'block_versions'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (Schema::hasColumn($table, 'content_legacy_blocknote')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->json('content_legacy_blocknote')->nullable()->after('content');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (! Schema::hasColumn($table, 'content_legacy_blocknote')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('content_legacy_blocknote');
            });
        }
    }
};
