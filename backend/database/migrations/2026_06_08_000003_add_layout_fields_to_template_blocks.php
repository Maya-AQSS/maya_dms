<?php

declare(strict_types=1);

use App\Enums\BlockType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1 de "layout blocks":
 *  - block_type: familia del bloque (content|cover|blank|index). Hasta ahora el
 *    "type" del frontend se ignoraba; esta columna lo materializa.
 *  - page_break_after: fuerza que el siguiente bloque empiece en página nueva
 *    al exportar a PDF.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_blocks', function (Blueprint $table) {
            $table->enum('block_type', BlockType::values())
                ->default(BlockType::Content->value)
                ->after('template_id');
            $table->boolean('page_break_after')
                ->default(false)
                ->after('block_state');
        });
    }

    public function down(): void
    {
        Schema::table('template_blocks', function (Blueprint $table) {
            $table->dropColumn(['block_type', 'page_break_after']);
        });
    }
};
