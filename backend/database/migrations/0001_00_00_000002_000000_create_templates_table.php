<?php

use App\Enums\TemplateVisibilityLevel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ancla de plantilla en un proceso (`process_id`). Identidad estable del recurso en catálogo y FKs desde documentos.
 *
 * Estado final (consolidado): SOLO id, process_id, theme_id, head_entity_version_id + timestamps + softDeletes.
 * Metadatos (nombre, descripción, etc.) viven en entity_versions.snapshot_data.
 *
 * head_entity_version_id FK: añadida en create_entity_versions (no aquí, porque entity_versions no existe aún).
 * theme_id FK: añadida en create_themes (no aquí, porque themes no existe aún).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('process_id')->constrained('processes')->restrictOnDelete();
            $table->uuid('theme_id')->nullable();
            $table->uuid('head_entity_version_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['process_id', 'id']);
            $table->index('theme_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
