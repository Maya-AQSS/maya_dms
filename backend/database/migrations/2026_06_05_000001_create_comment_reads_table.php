<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estado de lectura por usuario y comentario.
 *
 * Un comentario puede estar leído para un revisor y no leído para el creador;
 * por eso el estado vive aquí y no en `comments`.
 *
 * `user_id`: FK lógica al catálogo de usuarios (FDW / mock), sin FK física.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comment_reads', function (Blueprint $table): void {
            $table->string('user_id');
            $table->foreignUuid('comment_id')->constrained('comments')->cascadeOnDelete();
            $table->timestamp('read_at')->useCurrent();

            $table->primary(['user_id', 'comment_id']);
            $table->index('comment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comment_reads');
    }
};
