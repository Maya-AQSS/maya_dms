<?php

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
            $table->foreignUuid('base_version_id')->nullable()->constrained('entity_versions')->nullOnDelete();
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

        Schema::dropIfExists('entity_versions');
    }
};
