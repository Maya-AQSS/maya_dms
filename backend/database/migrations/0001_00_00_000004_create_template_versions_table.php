<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fila legacy por versión publicada de plantilla (append-only).
 *
 * El JSON completo de publicación vive en {@see \App\Models\EntityVersion} (`snapshot_data`), enlazado por
 * `entity_version_id`. `blocks_snapshot` puede ser null cuando existe ese enlace (sin duplicar JSON).
 *
 * En PostgreSQL se define forbid_append_only_mutation() y el trigger append-only; la misma función
 * se reutiliza en block_versions (migración posterior). En SQLite (tests) solo aplica la capa de aplicación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_id')->constrained('templates')->restrictOnDelete();
            // Opcional: vínculo con entity_versions; la FK se define en create_entity_versions_table.
            $table->uuid('entity_version_id')->nullable();
            $table->unsignedInteger('version_number');
            $table->json('blocks_snapshot')->nullable();
            $table->text('changelog');
            $table->string('published_by');
            $table->timestamp('published_at');
            $table->timestamps();

            $table->unique(['template_id', 'version_number']);
            $table->unique('entity_version_id');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION forbid_append_only_mutation() RETURNS trigger AS $$
BEGIN
  RAISE EXCEPTION '% is append-only', TG_TABLE_NAME;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER template_versions_append_only
  BEFORE UPDATE OR DELETE ON template_versions
  FOR EACH ROW EXECUTE PROCEDURE forbid_append_only_mutation();
SQL);
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS template_versions_append_only ON template_versions;');
            DB::unprepared('DROP FUNCTION IF EXISTS forbid_append_only_mutation();');
        }

        Schema::dropIfExists('template_versions');
    }
};
