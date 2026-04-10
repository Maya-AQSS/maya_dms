<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optimiza consultas por usuario (grupos a los que pertenece un usuario).
     * El unique (group_id, user_id) ya cubre búsquedas por grupo.
     */
    public function up(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            $table->index(['user_id', 'group_id'], 'group_members_user_group_idx');
        });
    }

    public function down(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            $table->dropIndex('group_members_user_group_idx');
        });
    }
};
