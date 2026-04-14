<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Membresías de usuario en grupos.
 *
 * Índice (user_id, group_id): optimiza listados de grupos por usuario.
 * El unique (group_id, user_id) ya cubre búsquedas por grupo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('group_id')->constrained('groups')->cascadeOnDelete();
            $table->string('user_id');           // FK lógica → users (FDW)
            $table->string('role')->default('member'); // member | admin
            $table->timestamps();

            $table->unique(['group_id', 'user_id']);
            $table->index(['user_id', 'group_id'], 'group_members_user_group_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_members');
    }
};
