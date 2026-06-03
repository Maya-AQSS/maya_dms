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
            $table->string('icon', 40)->nullable();
            $table->string('color', 7)->nullable();
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
        if (Schema::hasTable('processes')) {
            Schema::table('processes', function (Blueprint $table) {
                try {
                    $table->dropForeign(['process_parent_id']);
                } catch (\Throwable) {
                }
            });
        }
        Schema::dropIfExists('processes');
    }
};
