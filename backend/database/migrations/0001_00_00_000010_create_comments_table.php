<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comentarios polimórficos para plantillas y documentos.
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
            $table->boolean('resolved')->default(false);
            $table->string('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();

            $table->index(['commentable_type', 'commentable_id']);
            $table->index(['commentable_type', 'commentable_id', 'commentable_version']);
            $table->index(['commentable_type', 'commentable_id', 'commentable_version', 'resolved']);
            $table->index(['blockable_type', 'blockable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
