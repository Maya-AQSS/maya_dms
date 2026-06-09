<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the `deleted_at` column to existing `processes` tables.
 *
 * Soft deletes were enabled on the Process model (commit 1941f7f5) by editing the
 * already-applied create_processes migration, so databases provisioned before that
 * change never received the column. This delta backfills them; on fresh databases the
 * create migration already adds the column, so the guard makes this a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('processes', 'deleted_at')) {
            Schema::table('processes', function (Blueprint $table): void {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('processes', 'deleted_at')) {
            Schema::table('processes', function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }
    }
};
