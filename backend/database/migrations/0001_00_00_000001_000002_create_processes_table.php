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
            $table->uuid('process_parent_id')->nullable();
            $table->timestamps();

            $table->index('process_parent_id');
        });

        // FK self-referencial
        Schema::table('processes', function (Blueprint $table): void {
            $table->foreign('process_parent_id')
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
