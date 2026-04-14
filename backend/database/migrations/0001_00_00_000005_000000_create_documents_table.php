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
     * La columna organization_id + el Global Scope garantizan
     * que ningún usuario vea documentos de otra organización (IDOR).
     *
     * template_version_id: ancla a la versión publicada de plantilla usada al crear el documento (nullable
     * si el documento se creó antes de fijar versión o en flujos legacy).
     * study_type_id / study_id / module_id: referencias lógicas al catálogo académico (FDW); sin FK en BD.
     * softDeletes: borrado lógico; el Global Scope de acceso sigue aplicando a filas no eliminadas.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_id')->constrained('templates')->restrictOnDelete();
            $table->foreignUuid('template_version_id')
                ->nullable()
                ->after('template_id')
                ->constrained('template_versions')
                ->restrictOnDelete();
            $table->string('title');
            $table->string('organization_id');   // FK lógica → organización (FDW/contexto JWT)
            $table->string('study_type_id')->nullable();
            $table->string('study_id')->nullable();
            $table->string('module_id')->nullable();
            $table->string('created_by');        // FK lógica → users (FDW)
            $table->string('owner_id');          // puede diferir de created_by tras delegación
            $table->string('status')->default('draft'); // draft | in_review | published
            $table->integer('current_version')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
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
