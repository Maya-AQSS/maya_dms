<?php

declare(strict_types=1);

use App\Enums\BlockKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Añade el discriminador `kind` a los bloques de plantilla.
 *
 * Valores posibles (ver {@see App\Enums\BlockKind}):
 *   content (default) | cover | blank | toc
 *
 * `content` es el comportamiento existente: bloque BlockNote que fluye.
 * El resto son bloques especiales de página completa con CSS Paged Media.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_blocks', function (Blueprint $table): void {
            $table->string('kind', 16)
                ->default(BlockKind::Content->value)
                ->after('sort_order');

            $table->index(['template_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::table('template_blocks', function (Blueprint $table): void {
            $table->dropIndex(['template_id', 'kind']);
            $table->dropColumn('kind');
        });
    }
};
