<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cambia la clave de favoritos de plantilla de `template_id` (entidad lógica)
 * a `template_version_id` (entity_version.id), de modo que cada fila del
 * listado (variante live o published_fallback) puede ser marcada de forma
 * independiente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_favorite_templates', function (Blueprint $table): void {
            $table->dropPrimary(['user_id', 'template_id']);
            $table->dropForeign(['template_id']);
            $table->dropColumn('template_id');

            $table->foreignUuid('template_version_id')
                ->constrained('entity_versions')
                ->cascadeOnDelete();

            $table->primary(['user_id', 'template_version_id']);
        });
    }

    public function down(): void
    {
        Schema::table('user_favorite_templates', function (Blueprint $table): void {
            $table->dropPrimary(['user_id', 'template_version_id']);
            $table->dropForeign(['template_version_id']);
            $table->dropColumn('template_version_id');

            $table->foreignUuid('template_id')->constrained('templates')->cascadeOnDelete();
            $table->primary(['user_id', 'template_id']);
        });
    }
};
