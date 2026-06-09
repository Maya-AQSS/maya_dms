<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('versionable_type');
            $table->uuid('versionable_id');
            $table->unsignedInteger('version_number');
            // Auto-referencia: la FK se añade después del CREATE (PostgreSQL exige PK establecida).
            $table->uuid('base_version_id')->nullable();
            $table->json('change_set')->nullable();
            $table->string('status')->default('draft');
            $table->string('created_by');
            $table->string('published_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->text('changelog')->nullable();
            $table->json('snapshot_data')->nullable();
            $table->boolean('is_snapshot_immutable')->default(false);
            $table->timestamps();

            $table->unique(['versionable_type', 'versionable_id', 'version_number'], 'entity_versions_unique_per_entity');
            $table->index(['versionable_type', 'versionable_id'], 'entity_versions_versionable_idx');
            $table->index(['status'], 'entity_versions_status_idx');
        });

        Schema::table('entity_versions', function (Blueprint $table) {
            $table->foreign('base_version_id')
                ->references('id')
                ->on('entity_versions')
                ->nullOnDelete();
        });

        Schema::table('document_versions', function (Blueprint $table) {
            $table->foreign('entity_version_id')
                ->references('id')
                ->on('entity_versions')
                ->nullOnDelete();
        });

        /* documents.template_version_id → entity_versions.id (publicación de plantilla anclada). */
        if (Schema::hasTable('documents')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->foreign('template_version_id')
                    ->references('id')
                    ->on('entity_versions')
                    ->restrictOnDelete();
            });
        }

        /* Añade FKs a entity_versions desde templates y documents (head_entity_version_id). */
        if (Schema::hasTable('templates')) {
            Schema::table('templates', function (Blueprint $table) {
                $table->foreign('head_entity_version_id')
                    ->references('id')
                    ->on('entity_versions')
                    ->restrictOnDelete();
            });
        }

        if (Schema::hasTable('documents')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->foreign('head_entity_version_id')
                    ->references('id')
                    ->on('entity_versions')
                    ->restrictOnDelete();
            });
        }

        /* Añade FK desde user_favorite_templates a entity_versions */
        if (Schema::hasTable('user_favorite_templates')) {
            Schema::table('user_favorite_templates', function (Blueprint $table) {
                $table->foreign('template_version_id')
                    ->references('id')
                    ->on('entity_versions')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
CREATE TRIGGER entity_versions_append_only_snapshots
  BEFORE UPDATE OR DELETE ON entity_versions
  FOR EACH ROW
  WHEN (OLD.is_snapshot_immutable = true)
  EXECUTE PROCEDURE forbid_append_only_mutation();
SQL);
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS entity_versions_append_only_snapshots ON entity_versions;');
        }

        if (Schema::hasTable('user_favorite_templates')) {
            Schema::table('user_favorite_templates', function (Blueprint $table) {
                try {
                    $table->dropForeign(['template_version_id']);
                } catch (Throwable) {
                }
            });
        }

        if (Schema::hasTable('documents')) {
            Schema::table('documents', function (Blueprint $table) {
                try {
                    $table->dropForeign(['head_entity_version_id']);
                } catch (Throwable) {
                }
                try {
                    $table->dropForeign(['template_version_id']);
                } catch (Throwable) {
                }
            });
        }

        if (Schema::hasTable('templates')) {
            Schema::table('templates', function (Blueprint $table) {
                try {
                    $table->dropForeign(['head_entity_version_id']);
                } catch (Throwable) {
                }
            });
        }

        Schema::table('document_versions', function (Blueprint $table) {
            try {
                $table->dropForeign(['entity_version_id']);
            } catch (Throwable) {
            }
        });

        Schema::table('entity_versions', function (Blueprint $table) {
            try {
                $table->dropForeign(['base_version_id']);
            } catch (Throwable) {
            }
        });

        Schema::dropIfExists('entity_versions');
    }
};
