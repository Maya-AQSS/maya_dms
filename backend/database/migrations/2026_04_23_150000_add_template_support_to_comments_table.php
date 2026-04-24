<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Añade soporte para comentarios en plantillas normativas y sus bloques.
     */
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->foreignUuid('template_id')->nullable()->after('document_id')->constrained('templates')->cascadeOnDelete();
            $table->foreignUuid('template_block_id')->nullable()->after('document_block_id')->constrained('template_blocks')->cascadeOnDelete();
            
            // Hacer document_id nullable ya que ahora un comentario puede ser de plantilla
            $table->foreignUuid('document_id')->nullable()->change();
            
            $table->index(['template_id', 'template_block_id']);
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
            $table->dropForeign(['template_block_id']);
            $table->dropColumn(['template_id', 'template_block_id']);
            
            $table->foreignUuid('document_id')->nullable(false)->change();
        });
    }
};
