<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los bloques de plantilla definen la estructura de un documento.
     * Cada bloque tiene un tipo, un estado y un flag de obligatoriedad.
     *
     * Estados de bloque (F-03.3):
     *   - editable:   el autor puede escribir libremente
     *   - modifiable: el revisor puede proponer cambios (diff visual en F-06.3)
     *   - locked:     solo visible, no editable por ningún rol
     *
     * Flag mandatory: si es true, el bloque debe tener contenido antes
     * de que el documento pueda enviarse a revisión (F-04.5).
     */
    public function up(): void
    {
        Schema::create('template_blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_id')->constrained('templates')->cascadeOnDelete();
            $table->string('type');              // heading | paragraph | table | list | image | custom
            $table->string('title')->nullable();
            $table->json('default_content')->nullable(); // contenido BlockNote inicial
            $table->string('block_state')->default('editable'); // editable | modifiable | locked
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
