<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ancla de documento: vínculo a proceso, plantilla y publicación de plantilla usada al crear.
     *
     * Estado final (consolidado): SOLO id, process_id, template_id, template_version_id, head_entity_version_id + timestamps + softDeletes.
     * Metadatos (título, estado, etc.) viven en entity_versions.snapshot_data.
     *
     * FKs a entity_versions: añadidas en create_entity_versions (template_version_id, head_entity_version_id).
     * FK a templates: añadida directamente en Schema::create.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('process_id')->constrained('processes')->restrictOnDelete();
            $table->foreignUuid('template_id')->constrained('templates')->restrictOnDelete();
            $table->uuid('template_version_id')->nullable();
            $table->uuid('head_entity_version_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['process_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
