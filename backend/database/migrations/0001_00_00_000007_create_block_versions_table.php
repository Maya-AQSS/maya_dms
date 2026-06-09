<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Versionado append-only de bloques.
     * NUNCA se actualiza un registro existente: solo INSERT.
     * En PostgreSQL se reutiliza la función forbid_append_only_mutation() (migración append-only inicial).
     *
     * El diff entre versiones consecutivas se calcula en la capa de aplicación
     * y se muestra en el drawer de revisión.
     */
    public function up(): void
    {
        Schema::create('block_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_block_id')->constrained('document_blocks')->cascadeOnDelete();
            $table->foreignUuid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->integer('version_number');
            $table->json('content');            // snapshot del contenido BlockNote
            $table->json('diff')->nullable();   // diff respecto a la versión anterior
            $table->string('edited_by');         // FK lógica → users (FDW)
            $table->timestamp('created_at');     // solo created_at, no updated_at (inmutable)

            $table->unique(['document_block_id', 'version_number']);
            $table->index(['document_id', 'document_block_id']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
CREATE TRIGGER block_versions_append_only
  BEFORE UPDATE OR DELETE ON block_versions
  FOR EACH ROW EXECUTE PROCEDURE forbid_append_only_mutation();
SQL);
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS block_versions_append_only ON block_versions;');
        }

        Schema::dropIfExists('block_versions');
    }
};
