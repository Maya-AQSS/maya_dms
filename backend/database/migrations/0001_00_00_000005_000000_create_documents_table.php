<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ancla de documento: vínculo a proceso, plantilla y publicación de plantilla usada al crear ({@code template_version_id}
     * → {@see \App\Models\EntityVersion}); FK en create_entity_versions_table.
     *
     * Dominio (modelo objetivo): título, ámbito académico, plazos, titularidad/creador del trabajo en curso, estado
     * del ciclo (draft → in_review → published; in_review → draft si se rechaza) son atributos de una **versión de
     * documento** ({@see \App\Models\EntityVersion}), no de la entidad documento como mero id.
     *
     * Implementación actual: el borrador y el estado hasta publicar se persisten aquí; cada publicación documental
     * canónica vive en {@code entity_versions} (y revisiones en {@code document_reviews}). Igual que en plantillas,
     * mover el borrador completo a {@code entity_versions} implica refactor transversal.
     *
     * study_type_id / study_id / module_id: catálogo académico (FDW); sin FK física. softDeletes + scopes de acceso.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('process_id')->constrained('processes')->restrictOnDelete();
            $table->foreignUuid('template_id')->constrained('templates')->restrictOnDelete();
            $table->uuid('template_version_id')->nullable()->after('template_id');
            $table->string('title');
            $table->string('study_type_id')->nullable();
            $table->string('study_id')->nullable();
            $table->string('module_id')->nullable();
            $table->timestamp('delivery_deadline')->nullable();
            $table->string('created_by');        // FK lógica → users (FDW)
            $table->string('owner_id');          // puede diferir de created_by tras delegación
            $table->string('status')->default('draft'); // draft | in_review | published
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['process_id', 'id']);
            $table->index(['study_type_id', 'status']);
            $table->index(['study_id', 'status']);
            $table->index(['module_id', 'status']);
            $table->index('created_by');
            $table->index('owner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
