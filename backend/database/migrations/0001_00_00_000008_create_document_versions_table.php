<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fila legacy por versión de documento (append-only).
     *
     * El snapshot completo publicado también se guarda en {@see \App\Models\EntityVersion} (`snapshot_data`),
     * enlazado por `entity_version_id`. `snapshot_data` puede ser null en esa fila cuando existe enlace (sin duplicar JSON).
     * Otros `trigger_event` pueden seguir usando solo esta tabla.
     *
     * En PostgreSQL se reutiliza forbid_append_only_mutation() (migración append-only inicial).
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
            $table->json('snapshot_data')->nullable();
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
