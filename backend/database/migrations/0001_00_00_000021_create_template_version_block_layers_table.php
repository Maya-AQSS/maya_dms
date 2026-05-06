<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Capa incremental por versión de plantilla publicada: solo overrides o herencia explícita respecto a la versión anterior.
 * Convive con {@see template_versions.blocks_snapshot} (materialización completa) hasta migración total de lecturas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_version_block_layers', function (Blueprint $table) {
            $table->foreignUuid('template_version_id')->constrained('template_versions')->cascadeOnDelete();
            $table->uuid('template_block_id'); // identidad estable con {@see template_blocks.id}; sin FK por histórico
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('inherits_from_previous_publication')->default(false);
            $table->boolean('removed')->default(false);
            $table->json('override_payload')->nullable(); // definición completa del bloque cuando no hereda o está cambiado
            $table->timestamps();

            $table->primary(['template_version_id', 'template_block_id']);
            $table->index(['template_version_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_version_block_layers');
    }
};
