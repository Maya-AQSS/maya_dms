<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comentarios polimórficos para plantillas y documentos.
     * Estado final: sin resolved* columns (dropped), con updated_at/deleted_by/deleted_by_name.
     */
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('commentable_type');
            $table->uuid('commentable_id');
            $table->unsignedInteger('commentable_version')->default(1);
            $table->string('blockable_type')->nullable();
            $table->uuid('blockable_id')->nullable();
            $table->uuid('parent_id')->nullable(); // para respuestas anidadas
            $table->string('author_id');          // FK lógica → users (FDW)
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->softDeletes();
            $table->string('deleted_by')->nullable();
            $table->string('deleted_by_name')->nullable();

            $table->index(['commentable_type', 'commentable_id']);
            $table->index(['commentable_type', 'commentable_id', 'commentable_version']);
            $table->index(['blockable_type', 'blockable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
