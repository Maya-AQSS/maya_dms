<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AnchoredComment;
use App\Models\Comment;
use App\Models\Document;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AnchoredCommentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsJwtUser();
    }

    public function test_index_returns_anchored_comments_for_document(): void
    {
        $doc = Document::factory()->create();
        $comment = Comment::factory()->for($doc, 'commentable')->create();
        $anchor = AnchoredComment::factory()
            ->for($comment)
            ->create([
                'resource_type' => Document::class,
                'resource_id' => $doc->id,
            ]);

        $response = $this->getJson("/api/v1/document/{$doc->id}/anchored-comments");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $anchor->id);
    }

    public function test_store_creates_anchored_comment(): void
    {
        $doc = Document::factory()->create();
        $comment = Comment::factory()->for($doc, 'commentable')->create();

        $response = $this->postJson("/api/v1/document/{$doc->id}/anchored-comments", [
            'comment_id' => $comment->id,
            'anchor_from' => 10,
            'anchor_to' => 20,
            'anchor_text_snapshot' => 'selected text',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.comment_id', $comment->id);
        $this->assertDatabaseHas(AnchoredComment::class, [
            'comment_id' => $comment->id,
            'resource_type' => Document::class,
            'resource_id' => $doc->id,
            'anchor_from' => 10,
            'anchor_to' => 20,
        ]);
    }

    public function test_update_modifies_anchored_comment(): void
    {
        $doc = Document::factory()->create();
        $comment = Comment::factory()->for($doc, 'commentable')->create();
        $anchor = AnchoredComment::factory()
            ->for($comment)
            ->create([
                'resource_type' => Document::class,
                'resource_id' => $doc->id,
                'anchor_from' => 10,
                'anchor_to' => 20,
            ]);

        $response = $this->putJson(
            "/api/v1/document/{$doc->id}/anchored-comments/{$anchor->id}",
            [
                'anchor_from' => 15,
                'anchor_to' => 25,
                'anchor_text_snapshot' => 'new text',
            ]
        );

        $response->assertOk();
        $this->assertDatabaseHas(AnchoredComment::class, [
            'id' => $anchor->id,
            'anchor_from' => 15,
            'anchor_to' => 25,
        ]);
    }

    public function test_destroy_deletes_anchored_comment(): void
    {
        $doc = Document::factory()->create();
        $comment = Comment::factory()->for($doc, 'commentable')->create();
        $anchor = AnchoredComment::factory()
            ->for($comment)
            ->create([
                'resource_type' => Document::class,
                'resource_id' => $doc->id,
            ]);

        $response = $this->deleteJson("/api/v1/document/{$doc->id}/anchored-comments/{$anchor->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing(AnchoredComment::class, ['id' => $anchor->id]);
    }
}
