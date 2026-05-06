<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshots inmutables del documento completo.
     * Se crean en eventos clave: submit_for_review, publish, reject.
     * snapshot_data contiene el JSON completo del documento (todos sus bloques)
     * para poder reconstruir cualquier versión pasada sin joins.
     *
     * En PostgreSQL se reutiliza forbid_append_only_mutation() (migración template_versions).
     * En SQLite (tests) solo aplica la capa de aplicación.
     */
    public function up(): void
    {
        Schema::create('document_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained('documents')->cascadeOnDelete();
            // Opcional: vínculo con entity_versions; la FK se define en create_entity_versions_table.
            $table->uuid('entity_version_id')->nullable();
            $table->integer('version_number');
            $table->string('trigger_event');     // submitted | published | rejected
            $table->string('triggered_by');      // FK lógica → users (FDW)
            $table->json('snapshot_data');      // snapshot completo del documento
            $table->text('notes')->nullable();   // changelog de publicación, rechazo, etc.
            $table->boolean('is_immutable')->default(true);
            $table->timestamp('created_at');

            $table->unique(['document_id', 'version_number']);
            $table->unique('entity_version_id');
            $table->index(['document_id', 'version_number']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
CREATE TRIGGER document_versions_append_only
  BEFORE UPDATE OR DELETE ON document_versions
  FOR EACH ROW EXECUTE PROCEDURE forbid_append_only_mutation();
SQL);
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS document_versions_append_only ON document_versions;');
        }

        Schema::dropIfExists('document_versions');
    }
};
