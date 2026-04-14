<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshots inmutables de plantilla publicada.
 * En PostgreSQL se añade trigger append-only; en SQLite (tests) solo aplica la capa de aplicación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_id')->constrained('templates')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->json('blocks_snapshot');
            $table->text('changelog');
            $table->string('published_by');
            $table->timestamp('published_at');
            $table->timestamps();

            $table->unique(['template_id', 'version_number']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION forbid_template_versions_mutation() RETURNS trigger AS $$
BEGIN
  RAISE EXCEPTION 'template_versions es append-only';
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER template_versions_append_only
  BEFORE UPDATE OR DELETE ON template_versions
  FOR EACH ROW EXECUTE PROCEDURE forbid_template_versions_mutation();
SQL);
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS template_versions_append_only ON template_versions;');
            DB::unprepared('DROP FUNCTION IF EXISTS forbid_template_versions_mutation();');
        }

        Schema::dropIfExists('template_versions');
    }
};
