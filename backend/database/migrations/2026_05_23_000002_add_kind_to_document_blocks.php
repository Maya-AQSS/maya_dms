<?php

declare(strict_types=1);

use App\Enums\BlockKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Añade el discriminador `kind` a los bloques de documento.
 *
 * Mirror 1:1 de la migración análoga en template_blocks — un documento
 * hereda el `kind` del template_block del que viene, pero la columna se
 * almacena también en document_blocks para evitar joins en el render.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_blocks', function (Blueprint $table): void {
            $table->string('kind', 16)
                ->default(BlockKind::Content->value)
                ->after('sort_order');

            $table->index(['document_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::table('document_blocks', function (Blueprint $table): void {
            $table->dropIndex(['document_id', 'kind']);
            $table->dropColumn('kind');
        });
    }
};
