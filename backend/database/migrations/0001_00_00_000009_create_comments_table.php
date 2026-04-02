<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comentarios por bloque (F-06.2).
     * Los comentarios se muestran en un drawer lateral asociado al bloque.
     * Los comentarios de revisión tienen un estado resolved/unresolved.
     *
     * El Global Scope de Comment filtrará por organization_id del documento
     * padre para prevenir IDOR (F-01.4).
     */
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignUuid('document_block_id')->nullable()->constrained('document_blocks')->cascadeOnDelete();
            $table->uuid('parent_id')->nullable(); // para respuestas anidadas
            $table->string('author_id');          // FK lógica → users (FDW)
            $table->text('body');
            $table->string('type')->default('general'); // general | review
            $table->boolean('resolved')->default(false);
            $table->string('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['document_id', 'document_block_id']);
            $table->index(['document_id', 'type', 'resolved']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
