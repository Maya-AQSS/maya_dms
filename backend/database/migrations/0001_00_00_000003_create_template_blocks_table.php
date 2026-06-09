<?php

declare(strict_types=1);

use App\Enums\BlockState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los bloques de plantilla definen la estructura de un documento.
     * Se modelan por título, contenido por defecto y estado.
     *
     * Estados de bloque (coinciden con {@see BlockState}):
     * optional | editable | modifiable | locked
     */
    public function up(): void
    {
        Schema::create('template_blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_id')->constrained('templates')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->json('default_content')->nullable(); // contenido BlockNote inicial
            $table->text('description')->nullable();
            $table->enum('block_state', BlockState::values())
                ->default(BlockState::Editable->value);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['template_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_blocks');
    }
};
