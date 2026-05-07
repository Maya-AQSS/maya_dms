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
        if (! Schema::hasTable('templates')) {
            return;
        }

        // SQLite: hay que quitar índices que usan estas columnas antes de dropColumn (p. ej. templates_study_type_id_index).
        Schema::table('templates', function (Blueprint $table) {
            foreach (['templates_visibility_status_index'] as $name) {
                try {
                    $table->dropIndex($name);
                } catch (\Throwable) {
                }
            }
            foreach (['study_type_id', 'study_id', 'module_id', 'team_id'] as $col) {
                try {
                    $table->dropIndex([$col]);
                } catch (\Throwable) {
                }
            }
        });

        Schema::table('templates', function (Blueprint $table) {
            $drop = array_values(array_filter(
                [
                    'name',
                    'description',
                    'visibility_level',
                    'delivery_deadline',
                    'study_type_id',
                    'study_id',
                    'module_id',
                    'team_id',
                    'created_by',
                    'status',
                    'review_stages',
                    'review_mode',
                ],
                static fn (string $c): bool => Schema::hasColumn('templates', $c),
            ));
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('templates')) {
            return;
        }

        Schema::table('templates', function (Blueprint $table) {
            if (! Schema::hasColumn('templates', 'name')) {
                $table->string('name')->nullable();
            }
            if (! Schema::hasColumn('templates', 'description')) {
                $table->text('description')->nullable();
            }
            if (! Schema::hasColumn('templates', 'visibility_level')) {
                $table->string('visibility_level')->nullable();
            }
            if (! Schema::hasColumn('templates', 'delivery_deadline')) {
                $table->timestamp('delivery_deadline')->nullable();
            }
            if (! Schema::hasColumn('templates', 'study_type_id')) {
                $table->string('study_type_id')->nullable();
            }
            if (! Schema::hasColumn('templates', 'study_id')) {
                $table->string('study_id')->nullable();
            }
            if (! Schema::hasColumn('templates', 'module_id')) {
                $table->string('module_id')->nullable();
            }
            if (! Schema::hasColumn('templates', 'team_id')) {
                $table->uuid('team_id')->nullable()->index();
            }
            if (! Schema::hasColumn('templates', 'created_by')) {
                $table->string('created_by')->nullable();
            }
            if (! Schema::hasColumn('templates', 'status')) {
                $table->string('status')->default('draft');
            }
            if (! Schema::hasColumn('templates', 'review_stages')) {
                $table->integer('review_stages')->default(0);
            }
            if (! Schema::hasColumn('templates', 'review_mode')) {
                $table->string('review_mode')->default('parallel');
            }
        });

        Schema::table('templates', function (Blueprint $table) {
            if (Schema::hasColumn('templates', 'visibility_level') && Schema::hasColumn('templates', 'status')) {
                try {
                    $table->index(['visibility_level', 'status'], 'templates_visibility_status_index');
                } catch (\Throwable) {
                }
            }
        });
    }
};
