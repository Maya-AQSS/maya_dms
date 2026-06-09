<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comparticiones de documento con otros usuarios (lectura o edición).
     * Un par (document_id, user_id) solo puede existir una vez.
     */
    public function up(): void
    {
        Schema::create('document_shares', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('user_id');           // FK lógica → users (FDW)
            $table->string('permission')->default('read'); // read | edit
            $table->string('granted_by');
            $table->timestamps();

            $table->unique(['document_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_shares');
    }
};
