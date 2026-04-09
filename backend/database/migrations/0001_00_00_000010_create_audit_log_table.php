<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla de auditoría inmutable para cumplimiento ISO 9001 y RGPD.
     *
     * Restricciones de integridad:
     *   - El usuario de aplicación solo tiene permiso INSERT + SELECT.
     *   - UPDATE y DELETE están revocados a nivel de PostgreSQL.
     *   - El campo timestamp siempre se establece con NOW() del servidor;
     *     nunca puede ser enviado por el cliente.
     */
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('entity_type');  // document | template | comment
            $table->uuid('entity_id');
            $table->uuid('block_uuid')->nullable();

            // created | updated | deleted | state_changed | approved | rejected
            $table->string('action');

            $table->string('user_id');      // FK lógica → users (FDW)
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // Timestamp siempre del servidor — DEFAULT NOW(), nunca enviado por cliente
            $table->timestamp('timestamp')->useCurrent();

            $table->json('previous_value')->nullable();
            $table->json('new_value')->nullable();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['entity_id', 'action']);
            $table->index('user_id');
            $table->index('timestamp');
        });

        // El usuario de aplicación solo puede INSERT y SELECT.
        // UPDATE y DELETE están prohibidos a nivel de PostgreSQL.
        if (DB::getDriverName() === 'pgsql') {
            $appUser = config('database.connections.pgsql.username');
            DB::statement("REVOKE UPDATE, DELETE ON audit_log FROM \"{$appUser}\"");
            DB::statement("GRANT INSERT, SELECT ON audit_log TO \"{$appUser}\"");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $appUser = config('database.connections.pgsql.username');
            DB::statement("GRANT UPDATE, DELETE ON audit_log TO \"{$appUser}\"");
        }

        Schema::dropIfExists('audit_log');
    }
};
