<?php

declare(strict_types=1);

use App\Enums\BlockState;
use App\Enums\BlockType;
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
            $table->enum('block_type', BlockType::values())
                ->default(BlockType::Content->value);
            // theme_id FK: se añade en create_themes (no aquí, porque themes no existe
            // todavía en este punto del orden de migraciones; mismo patrón que templates.theme_id).
            $table->uuid('theme_id')->nullable();
            $table->boolean('apply_theme')->default(true);
            $table->string('title')->nullable();
            $table->json('default_content')->nullable(); // contenido BlockNote inicial
            $table->text('description')->nullable();
            $table->enum('block_state', BlockState::values())
                ->default(BlockState::Editable->value);
            $table->boolean('page_break_after')->default(false);
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
