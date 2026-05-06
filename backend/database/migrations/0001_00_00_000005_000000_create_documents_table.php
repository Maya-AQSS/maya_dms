<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ciclo de vida del documento:
     *   draft → in_review → published
     *   in_review → draft  (si se rechaza)
     *
     * template_version_id: UUID de entity_versions (publicación de plantilla). La FK se añade en la migración
     * create_entity_versions_table una vez creada entity_versions.
     * study_type_id / study_id / module_id: referencias lógicas al catálogo académico (FDW); sin FK en BD.
     * softDeletes: borrado lógico; el Global Scope de acceso sigue aplicando a filas no eliminadas.
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
            $table->integer('current_version')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('published_at')->nullable();
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
