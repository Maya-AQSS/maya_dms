<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anchored-comment support: comments pinned to a ProseMirror position range
 * inside a TipTap document. The existing `comments` table keeps the user-
 * facing payload (author, content, edits, soft-deletes); this table holds
 * the position + rebasement state per-comment.
 *
 * - `resource_type` / `resource_id` are polymorphic — currently `Template`
 *   and `Document`, but the schema allows any model. Authorization is
 *   enforced at the controller level via `$this->authorize('update', $resource)`.
 * - `anchor_from` / `anchor_to` are ProseMirror positions; rebased by the
 *   editor on every transaction via `Transaction.mapping`.
 * - `anchor_text_snapshot` keeps the original anchored text for audit (e.g.
 *   "this paragraph was deleted, here was what the reviewer wrote about").
 * - `anchor_is_valid` flips to `false` when a transaction collapses the
 *   range to zero width.
 *
 * Comments without an anchored counterpart (thread-style replies, general
 * comments on a resource) keep using the `comments` table directly with
 * no row here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anchored_comments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('comment_id')
                ->unique()
                ->constrained('comments')
                ->cascadeOnDelete();
            $t->string('resource_type', 64);
            $t->uuid('resource_id');
            $t->unsignedInteger('anchor_from');
            $t->unsignedInteger('anchor_to');
            $t->string('anchor_text_snapshot', 1000)->nullable();
            $t->boolean('anchor_is_valid')->default(true);
            $t->timestamp('anchor_last_synced_at')->nullable();
            $t->timestamps();

            $t->index(['resource_type', 'resource_id'], 'anchored_comments_resource_idx');
            $t->index('anchor_is_valid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anchored_comments');
    }
};
