<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Capa incremental por publicación de plantilla ({@see entity_versions}): overrides o herencia explícita.
 * La PK compuesta referencia el id de la fila publicada en {@see entity_versions}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_version_block_layers', function (Blueprint $table) {
            $table->foreignUuid('entity_version_id')->constrained('entity_versions')->cascadeOnDelete();
            $table->uuid('template_block_id'); // identidad estable con {@see template_blocks.id}; sin FK por histórico
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('inherits_from_previous_publication')->default(false);
            $table->boolean('removed')->default(false);
            $table->json('override_payload')->nullable();
            $table->timestamps();

            $table->primary(['entity_version_id', 'template_block_id']);
            $table->index(['entity_version_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_version_block_layers');
    }
};
