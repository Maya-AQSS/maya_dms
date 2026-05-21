<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permite asignar un Theme reutilizable a una plantilla. Los documentos generados
 * a partir de esa plantilla heredan el theme en render (preview HTML y export PDF).
 *
 * Nullable: una plantilla sin theme renderiza con el theme por defecto del sistema.
 *
 * Restrict on delete del theme: una plantilla que está usando el theme bloquea su
 * borrado (forzar archive del theme en su lugar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->foreignUuid('theme_id')
                ->nullable()
                ->after('process_id')
                ->constrained('themes')
                ->restrictOnDelete();

            $table->index('theme_id', 'templates_theme_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropForeign(['theme_id']);
            $table->dropIndex('templates_theme_id_index');
            $table->dropColumn('theme_id');
        });
    }
};
