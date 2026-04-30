<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 100)->unique();
            $table->string('name');
            $table->string('alias');
            $table->text('description')->nullable();
            $table->uuid('parent_id')->nullable();
            $table->index('parent_id');
            $table->timestamps();
        });

        // FK self-referencial: la añadimos después de crear la tabla porque
        // Postgres exige que la unique/PK de la columna referenciada exista
        // antes de aceptar la FK (no se puede en el mismo CREATE TABLE).
        Schema::table('processes', function (Blueprint $table): void {
            $table->foreign('parent_id')
                ->references('id')
                ->on('processes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processes');
    }
};
