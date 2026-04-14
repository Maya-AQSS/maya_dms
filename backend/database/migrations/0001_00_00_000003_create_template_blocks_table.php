<?php

use App\Enums\BlockState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los bloques de plantilla definen la estructura de un documento.
     * Cada bloque tiene un tipo, un estado y un flag de obligatoriedad.
     *
     * Estados de bloque (coinciden con {@see BlockState}).
     *
     * Flag mandatory: si es true, el bloque debe tener contenido antes
     * de que el documento pueda enviarse a revisión.
     */
    public function up(): void
    {
        Schema::create('template_blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_id')->constrained('templates')->cascadeOnDelete();
            $table->string('type');              // heading | paragraph | table | list | image | custom
            $table->string('title')->nullable();
            $table->json('default_content')->nullable(); // contenido BlockNote inicial
            $table->enum('block_state', BlockState::values())
                ->default(BlockState::Editable->value);
            $table->boolean('mandatory')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['template_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_blocks');
    }
};
