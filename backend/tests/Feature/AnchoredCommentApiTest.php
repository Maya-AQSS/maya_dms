<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AnchoredComment;
use App\Models\Comment;
use App\Models\Document;
use App\Models\Process;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Maya\Auth\Contracts\JwksServiceInterface;
use Tests\Concerns\AssignsTestUserPermissions;
use Tests\Concerns\BuildsTestJwt;
use Tests\TestCase;

final class AnchoredCommentApiTest extends TestCase
{
    use AssignsTestUserPermissions;
    use BuildsTestJwt;
    use RefreshDatabase;

    private string $userId;
    private array $authHeaders;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth.jwt_issuer' => 'test-issuer',
            'auth.jwt_audience' => 'test-audience',
        ]);

        Cache::flush();

        $this->userId = (string) Str::uuid();
        $this->assignUserPermissions($this->userId, []);
        $this->authHeaders = $this->buildAuthHeaders($this->userId);
    }

    private function buildAuthHeaders(string $sub, array $realmRoles = [], array $extraClaims = []): array
    {
        auth()->forgetUser();

        [$privatePem, $publicPem] = $this->generateRsaKeyPairForTests();

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn($publicPem);

        $token = $this->buildJwtForSub(
            $privatePem,
            $publicPem,
            'kid-'.substr($sub, 0, 8),
            $sub,
            'test-issuer',
            'test-audience',
            $realmRoles,
            $extraClaims,
        );

        return ['Authorization' => 'Bearer '.$token];
    }

    private function createDocument(string $createdBy, string $title = 'Test Doc'): string
    {
        $processId = Process::query()->value('id') ?? (string) Str::uuid();
        if (!Process::query()->where('id', $processId)->exists()) {
            Process::query()->forceCreate([
                'id' => $processId,
                'name' => 'Test Process',
                'status' => 'draft',
            ]);
        }

        $templateId = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $templateId,
            'process_id' => $processId,
            'name' => 'Test Template',
            'created_by' => $createdBy,
            'status' => 'draft',
            'visibility_level' => 'personal',
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        $docId = (string) Str::uuid();
        Document::query()->forceCreate([
            'id' => $docId,
            'created_by' => $createdBy,
            'owner_id' => $createdBy,
            'template_id' => $templateId,
            'process_id' => $processId,
            'title' => $title,
            'status' => 'draft',
        ]);

        return $docId;
    }

    public function test_index_returns_anchored_comments_for_document(): void
    {
        $docId = $this->createDocument($this->userId);

        $commentId = (string) Str::uuid();
        Comment::query()->forceCreate([
            'id' => $commentId,
            'commentable_type' => Document::class,
            'commentable_id' => $docId,
            'body' => 'test',
            'author_id' => $this->userId,
        ]);

        $anchorId = (string) Str::uuid();
        AnchoredComment::query()->forceCreate([
            'id' => $anchorId,
            'comment_id' => $commentId,
            'resource_type' => 'document',
            'resource_id' => $docId,
            'anchor_from' => 10,
            'anchor_to' => 20,
            'anchor_text_snapshot' => 'text',
            'anchor_is_valid' => true,
            'anchor_last_synced_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/document/{$docId}/anchored-comments", $this->authHeaders);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $anchorId);
    }

    public function test_store_creates_anchored_comment(): void
    {
        $docId = $this->createDocument($this->userId);

        $commentId = (string) Str::uuid();
        Comment::query()->forceCreate([
            'id' => $commentId,
            'commentable_type' => Document::class,
            'commentable_id' => $docId,
            'body' => 'test',
            'author_id' => $this->userId,
        ]);

        $response = $this->postJson("/api/v1/document/{$docId}/anchored-comments", [
            'comment_id' => $commentId,
            'anchor_from' => 10,
            'anchor_to' => 20,
            'anchor_text_snapshot' => 'selected text',
        ], $this->authHeaders);

        $response->assertStatus(201);
        $response->assertJsonPath('data.comment_id', $commentId);
        $this->assertDatabaseHas(AnchoredComment::class, [
            'comment_id' => $commentId,
            'resource_type' => 'document',
            'resource_id' => $docId,
            'anchor_from' => 10,
            'anchor_to' => 20,
        ]);
    }

    public function test_update_modifies_anchored_comment(): void
    {
        $docId = $this->createDocument($this->userId);

        $commentId = (string) Str::uuid();
        Comment::query()->forceCreate([
            'id' => $commentId,
            'commentable_type' => Document::class,
            'commentable_id' => $docId,
            'body' => 'test',
            'author_id' => $this->userId,
        ]);

        $anchorId = (string) Str::uuid();
        AnchoredComment::query()->forceCreate([
            'id' => $anchorId,
            'comment_id' => $commentId,
            'resource_type' => 'document',
            'resource_id' => $docId,
            'anchor_from' => 10,
            'anchor_to' => 20,
            'anchor_text_snapshot' => 'text',
            'anchor_is_valid' => true,
            'anchor_last_synced_at' => now(),
        ]);

        $response = $this->putJson(
            "/api/v1/document/{$docId}/anchored-comments/{$anchorId}",
            [
                'anchor_from' => 15,
                'anchor_to' => 25,
                'anchor_text_snapshot' => 'new text',
            ],
            $this->authHeaders
        );

        $response->assertOk();
        $this->assertDatabaseHas(AnchoredComment::class, [
            'id' => $anchorId,
            'anchor_from' => 15,
            'anchor_to' => 25,
        ]);
    }

    public function test_destroy_deletes_anchored_comment(): void
    {
        $docId = $this->createDocument($this->userId);

        $commentId = (string) Str::uuid();
        Comment::query()->forceCreate([
            'id' => $commentId,
            'commentable_type' => Document::class,
            'commentable_id' => $docId,
            'body' => 'test',
            'author_id' => $this->userId,
        ]);

        $anchorId = (string) Str::uuid();
        AnchoredComment::query()->forceCreate([
            'id' => $anchorId,
            'comment_id' => $commentId,
            'resource_type' => 'document',
            'resource_id' => $docId,
            'anchor_from' => 10,
            'anchor_to' => 20,
            'anchor_text_snapshot' => 'text',
            'anchor_is_valid' => true,
            'anchor_last_synced_at' => now(),
        ]);

        $response = $this->deleteJson("/api/v1/document/{$docId}/anchored-comments/{$anchorId}", [], $this->authHeaders);

        $response->assertNoContent();
        $this->assertDatabaseMissing(AnchoredComment::class, ['id' => $anchorId]);
    }

    public function test_unauthorized_user_cannot_access_anchored_comments(): void
    {
        $docId = $this->createDocument($this->userId);

        $commentId = (string) Str::uuid();
        Comment::query()->forceCreate([
            'id' => $commentId,
            'commentable_type' => Document::class,
            'commentable_id' => $docId,
            'body' => 'test',
            'author_id' => $this->userId,
        ]);

        AnchoredComment::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'comment_id' => $commentId,
            'resource_type' => 'document',
            'resource_id' => $docId,
            'anchor_from' => 10,
            'anchor_to' => 20,
            'anchor_text_snapshot' => 'text',
            'anchor_is_valid' => true,
            'anchor_last_synced_at' => now(),
        ]);

        $otherUserId = (string) Str::uuid();
        $this->assignUserPermissions($otherUserId, [], false);
        $otherHeaders = $this->buildAuthHeaders($otherUserId);

        $response = $this->getJson("/api/v1/document/{$docId}/anchored-comments", $otherHeaders);

        $response->assertForbidden();
    }
}
