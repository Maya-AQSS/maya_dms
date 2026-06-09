<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * document_blocks son los bloques instanciados de template_blocks para un documento.
     * El contenido actual siempre vive aquí; el historial en block_versions.
     *
     * El campo locked_by + locked_at implementa el lock optimista para
     * edición colaborativa en tiempo real (Reverb).
     */
    public function up(): void
    {
        Schema::create('document_blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignUuid('template_block_id')->constrained('template_blocks')->restrictOnDelete();
            $table->json('content')->nullable();     // contenido BlockNote actual
            $table->boolean('is_filled')->default(false); // para validación de obligatorios
            $table->string('last_edited_by')->nullable();
            $table->string('locked_by')->nullable();  // FK lógica → users (FDW)
            $table->timestamp('locked_at')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['document_id', 'template_block_id']);
            $table->index(['document_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_blocks');
    }
};
