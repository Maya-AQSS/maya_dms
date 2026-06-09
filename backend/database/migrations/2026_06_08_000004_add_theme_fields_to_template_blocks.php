<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 de "layout blocks": tema por bloque.
 *  - theme_id: override del tema por bloque (nullable). El tema por defecto
 *    sigue en templates.theme_id; si el bloque no define theme_id, hereda ese.
 *  - apply_theme: si false, el bloque no lleva tema en absoluto (ni colores/
 *    tipografía ni cabecera/pie/marca) y ocupa su propia página.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_blocks', function (Blueprint $table) {
            $table->foreignUuid('theme_id')
                ->nullable()
                ->after('block_type')
                ->constrained('themes')
                ->nullOnDelete();
            $table->boolean('apply_theme')
                ->default(true)
                ->after('theme_id');
        });
    }

    public function down(): void
    {
        Schema::table('template_blocks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('theme_id');
            $table->dropColumn('apply_theme');
        });
    }
};
