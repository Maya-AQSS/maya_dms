<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Capa incremental por versión de documento publicado (document_versions). Misma idea que template_version_block_layers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_version_block_layers', function (Blueprint $table) {
            $table->foreignUuid('document_version_id')->constrained('document_versions')->cascadeOnDelete();
            $table->uuid('document_block_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('inherits_from_previous_publication')->default(false);
            $table->boolean('removed')->default(false);
            $table->json('override_payload')->nullable();
            $table->timestamps();

            $table->primary(['document_version_id', 'document_block_id']);
            $table->index(['document_version_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_version_block_layers');
    }
};
