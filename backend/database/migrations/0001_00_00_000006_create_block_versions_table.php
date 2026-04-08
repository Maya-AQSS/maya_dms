<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Versionado append-only de bloques (F-04.4).
     * NUNCA se actualiza un registro existente: solo INSERT.
     * La tabla es inmutable por convención — no hay UPDATE/DELETE en el código.
     *
     * El diff entre versiones consecutivas se calcula en la capa de aplicación
     * y se muestra en el drawer de revisión (F-06.3).
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
    }

    public function down(): void
    {
        Schema::dropIfExists('block_versions');
    }
};
