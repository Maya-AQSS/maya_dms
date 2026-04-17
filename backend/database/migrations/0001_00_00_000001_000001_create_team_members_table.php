<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Membresías de usuario en equipos.
 *
 * Índice (user_id, team_id): optimiza listados por usuario.
 * El unique (team_id, user_id) cubre búsquedas por equipo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Sin FK física: `teams` es vista sobre catálogo externo (FDW); PostgreSQL no admite REFERENCES a vistas.
            $table->uuid('team_id');
            $table->string('user_id'); // FK lógica → users (FDW)
            $table->string('role')->default('member'); // member | admin
            $table->timestamps();

            $table->unique(['team_id', 'user_id']);
            $table->index(['user_id', 'team_id'], 'team_members_user_team_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_members');
    }
};
