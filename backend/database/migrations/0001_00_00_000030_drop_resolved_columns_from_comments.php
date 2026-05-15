<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex(['commentable_type', 'commentable_id', 'commentable_version', 'resolved']);
            $table->dropColumn(['resolved', 'resolved_by', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->boolean('resolved')->default(false);
            $table->string('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->index(['commentable_type', 'commentable_id', 'commentable_version', 'resolved']);
        });
    }
};
