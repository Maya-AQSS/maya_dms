<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Metadatos de trabajo en {@see entity_versions} (versión cabezal número 0).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('documents')) {
            return;
        }

        foreach (
            [
                ['study_type_id', 'status'],
                ['study_id', 'status'],
                ['module_id', 'status'],
                'created_by',
                'owner_id',
            ] as $index
        ) {
            Schema::table('documents', function (Blueprint $table) use ($index) {
                try {
                    if (is_array($index)) {
                        $table->dropIndex($index);
                    } else {
                        $table->dropIndex([$index]);
                    }
                } catch (\Throwable) {
                }
            });
        }

        Schema::table('documents', function (Blueprint $table) {
            $drop = array_values(array_filter(
                [
                    'title',
                    'study_type_id',
                    'study_id',
                    'module_id',
                    'delivery_deadline',
                    'created_by',
                    'owner_id',
                    'status',
                ],
                static fn (string $c): bool => Schema::hasColumn('documents', $c),
            ));
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('documents')) {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            if (! Schema::hasColumn('documents', 'title')) {
                $table->string('title')->nullable();
            }
            if (! Schema::hasColumn('documents', 'study_type_id')) {
                $table->string('study_type_id')->nullable();
            }
            if (! Schema::hasColumn('documents', 'study_id')) {
                $table->string('study_id')->nullable();
            }
            if (! Schema::hasColumn('documents', 'module_id')) {
                $table->string('module_id')->nullable();
            }
            if (! Schema::hasColumn('documents', 'delivery_deadline')) {
                $table->timestamp('delivery_deadline')->nullable();
            }
            if (! Schema::hasColumn('documents', 'created_by')) {
                $table->string('created_by')->nullable();
            }
            if (! Schema::hasColumn('documents', 'owner_id')) {
                $table->string('owner_id')->nullable();
            }
            if (! Schema::hasColumn('documents', 'status')) {
                $table->string('status')->default('draft');
            }
        });

        Schema::table('documents', function (Blueprint $table) {
            try {
                $table->index(['study_type_id', 'status']);
            } catch (\Throwable) {
            }
            try {
                $table->index(['study_id', 'status']);
            } catch (\Throwable) {
            }
            try {
                $table->index(['module_id', 'status']);
            } catch (\Throwable) {
            }
            try {
                $table->index('created_by');
            } catch (\Throwable) {
            }
            try {
                $table->index('owner_id');
            } catch (\Throwable) {
            }
        });
    }
};
