<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BlockState;
use App\Enums\TemplateVisibilityLevel;
use App\Models\Comment;
use App\Models\Document;
use App\Models\DocumentBlock;
use App\Models\Template;
use App\Models\TemplateBlock;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\UserPermissionsSeeder;
use Database\Seeders\UsersSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maya\Auth\Contracts\JwksServiceInterface;
use Tests\Concerns\AssignsTestUserPermissions;
use Tests\Concerns\BuildsTestJwt;
use Tests\Concerns\SeedsTemplatePublicationAnchor;
use Tests\TestCase;

/**
 * Feature tests for CommentController (index, store, show, destroy).
 *
 * Primary targets:
 *   - CommentService::assertBlockBelongsToResource  (lines 110-153)
 *   - CommentService::assertParentBelongsToResource (lines 155-198)
 *   - CommentController::authorizeCommentAccess abort(422) at line 168
 *
 * Coverage plan:
 *   assertBlockBelongsToResource:
 *     [A] blockable passed for template comment → wrong blockable_type → 422
 *     [B] blockable passed for template comment → blockable not in template → 422
 *     [C] blockable passed for document comment → wrong blockable_type → 422
 *     [D] blockable passed for document comment → blockable not in document → 422
 *     [E] blockable matches template block → 201 (happy path with block)
 *     [F] blockable matches document block → 201 (happy path with block)
 *
 *   assertParentBelongsToResource:
 *     [G] parent_id exists but deleted → 422
 *     [H] parent_id belongs to different resource → 422
 *     [I] parent_id belongs to different block → 422
 *     [J] parent_id belongs to same resource/block → 201 (happy path with parent)
 */
class CommentApiTest extends TestCase
{
    use AssignsTestUserPermissions;
    use BuildsTestJwt;
    use RefreshDatabase;
    use SeedsTemplatePublicationAnchor;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth.jwt_issuer'   => 'test-issuer',
            'auth.jwt_audience' => 'test-audience',
        ]);

        Cache::flush();

        $this->seed(UsersSourceSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(UserPermissionsSeeder::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * @param  list<string>  $codes
     * @return array<string, string>
     */
    private function authHeaders(
        string $sub,
        array $codes = ['templates.read', 'documents.read'],
    ): array {
        auth()->forgetUser();
        $this->assignUserPermissions($sub, $codes);

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
        );

        return ['Authorization' => 'Bearer '.$token];
    }

    /**
     * Seeds a personal draft template with one TemplateBlock.
     * Returns [ownerId, templateId, blockId].
     *
     * @return array{ownerId: string, templateId: string, blockId: string}
     */
    private function seedDraftTemplateWithBlock(): array
    {
        $ownerId    = (string) Str::uuid();
        $templateId = (string) Str::uuid();
        $blockId    = (string) Str::uuid();

        Template::query()->forceCreate([
            'id'               => $templateId,
            'process_id'       => '00000000-0000-0000-0000-000000000001',
            'name'             => 'Plantilla Comentarios',
            'description'      => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'created_by'       => $ownerId,
            'status'           => 'draft',
            'review_stages'    => 0,
            'review_mode'      => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id'              => $blockId,
            'template_id'     => $templateId,
            'title'           => 'Bloque Comentarios',
            'default_content' => null,
            'block_state'     => BlockState::Editable->value,
            'sort_order'      => 0,
        ]);

        return [
            'ownerId'    => $ownerId,
            'templateId' => $templateId,
            'blockId'    => $blockId,
        ];
    }

    /**
     * Seeds a personal draft template WITHOUT blocks (used when we need a template
     * but specifically pass a foreign block ID).
     *
     * @return array{ownerId: string, templateId: string}
     */
    private function seedDraftTemplateNoBlocks(): array
    {
        $ownerId    = (string) Str::uuid();
        $templateId = (string) Str::uuid();

        Template::query()->forceCreate([
            'id'               => $templateId,
            'process_id'       => '00000000-0000-0000-0000-000000000001',
            'name'             => 'Plantilla Sin Bloques',
            'description'      => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'created_by'       => $ownerId,
            'status'           => 'draft',
            'review_stages'    => 0,
            'review_mode'      => 'sequential',
        ]);

        return ['ownerId' => $ownerId, 'templateId' => $templateId];
    }

    /**
     * Seeds a draft document with one editable DocumentBlock and one optional DocumentBlock.
     * The template publication anchor is required so the document can reference a valid version.
     *
     * @return array{ownerId: string, documentId: string, editableBlockId: string, optionalBlockId: string, templateBlockId: string}
     */
    private function seedDraftDocumentWithBlocks(): array
    {
        $ownerId       = (string) Str::uuid();
        $templateId    = (string) Str::uuid();
        $tplBlockId    = (string) Str::uuid();
        $documentId    = (string) Str::uuid();
        $docBlockId    = (string) Str::uuid();
        $optDocBlockId = (string) Str::uuid();
        $optTplBlockId = (string) Str::uuid();

        Template::query()->forceCreate([
            'id'               => $templateId,
            'process_id'       => '00000000-0000-0000-0000-000000000001',
            'name'             => 'Plantilla para DocComments',
            'description'      => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'created_by'       => $ownerId,
            'status'           => 'draft',
            'review_stages'    => 0,
            'review_mode'      => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id'              => $tplBlockId,
            'template_id'     => $templateId,
            'title'           => 'Bloque Editable',
            'default_content' => null,
            'block_state'     => BlockState::Editable->value,
            'sort_order'      => 0,
        ]);

        TemplateBlock::query()->forceCreate([
            'id'              => $optTplBlockId,
            'template_id'     => $templateId,
            'title'           => 'Bloque Opcional',
            'default_content' => null,
            'block_state'     => BlockState::Optional->value,
            'sort_order'      => 1,
        ]);

        $anchor = $this->seedCanonicalPublicationForTemplate(
            $templateId,
            1,
            $ownerId,
            [
                [
                    'id'              => $tplBlockId,
                    'title'           => 'Bloque Editable',
                    'default_content' => null,
                    'block_state'     => BlockState::Editable->value,
                    'sort_order'      => 0,
                    'type'            => '',
                    'mandatory'       => true,
                ],
                [
                    'id'              => $optTplBlockId,
                    'title'           => 'Bloque Opcional',
                    'default_content' => null,
                    'block_state'     => BlockState::Optional->value,
                    'sort_order'      => 1,
                    'type'            => '',
                    'mandatory'       => false,
                ],
            ],
        );

        Document::query()->forceCreate([
            'id'                  => $documentId,
            'process_id'          => '00000000-0000-0000-0000-000000000001',
            'template_id'         => $templateId,
            'template_version_id' => $anchor['entity_version_id'],
            'title'               => 'Documento Comentarios',
            'created_by'          => $ownerId,
            'owner_id'            => $ownerId,
            'status'              => 'draft',
        ]);

        DocumentBlock::query()->forceCreate([
            'id'                => $docBlockId,
            'document_id'       => $documentId,
            'template_block_id' => $tplBlockId,
            'content'           => null,
            'is_filled'         => false,
            'sort_order'        => 0,
        ]);

        DocumentBlock::query()->forceCreate([
            'id'                => $optDocBlockId,
            'document_id'       => $documentId,
            'template_block_id' => $optTplBlockId,
            'content'           => null,
            'is_filled'         => false,
            'sort_order'        => 1,
        ]);

        return [
            'ownerId'       => $ownerId,
            'documentId'    => $documentId,
            'editableBlockId'  => $docBlockId,
            'optionalBlockId'  => $optDocBlockId,
            'templateBlockId'  => $tplBlockId,
        ];
    }

    /**
     * Inserts a Comment row directly using DB and returns its ID.
     * Does NOT use Comment::create so we bypass the user_access scope.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function insertComment(array $overrides = []): string
    {
        $id = (string) Str::uuid();

        DB::table('comments')->insert(array_merge([
            'id'                 => $id,
            'commentable_type'   => Template::class,
            'commentable_id'     => (string) Str::uuid(),
            'commentable_version'=> 1,
            'blockable_type'     => null,
            'blockable_id'       => null,
            'parent_id'          => null,
            'author_id'          => (string) Str::uuid(),
            'body'               => 'Comentario de prueba',
            'deleted_at'         => null,
            'created_at'         => now(),
        ], $overrides));

        return $id;
    }

    // ─── index — template ─────────────────────────────────────────────────────

    public function test_index_template_comments_requires_authentication(): void
    {
        $this->getJson('/api/v1/templates/'.Str::uuid().'/comments')
            ->assertUnauthorized();
    }

    public function test_index_template_comments_returns_404_for_unknown_template(): void
    {
        $userId  = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $this->getJson('/api/v1/templates/'.Str::uuid().'/comments', $headers)
            ->assertNotFound();
    }

    public function test_index_template_comments_returns_empty_list_for_owner(): void
    {
        $ctx     = $this->seedDraftTemplateWithBlock();
        $headers = $this->authHeaders($ctx['ownerId']);

        $response = $this->getJson("/api/v1/templates/{$ctx['templateId']}/comments", $headers)
            ->assertOk();

        $this->assertIsArray($response->json('data'));
        $this->assertCount(0, $response->json('data'));
        $this->assertTrue($response->json('meta.commenting_open'));
    }

    public function test_index_document_comments_returns_empty_list_for_owner(): void
    {
        $ctx     = $this->seedDraftDocumentWithBlocks();
        $headers = $this->authHeaders($ctx['ownerId']);

        $response = $this->getJson("/api/v1/documents/{$ctx['documentId']}/comments", $headers)
            ->assertOk();

        $this->assertIsArray($response->json('data'));
        $this->assertCount(0, $response->json('data'));
        $this->assertTrue($response->json('meta.commenting_open'));
    }

    public function test_non_owner_with_read_permission_can_index_comments_but_cannot_store_comments(): void
    {
        $otherUserId = (string) Str::uuid();

        // 1. Template: User B is assigned as a reviewer of a draft template owned by User A.
        $tplCtx = $this->seedDraftTemplateWithBlock();
        DB::table('template_reviewers')->insert([
            'id' => (string) Str::uuid(),
            'template_id' => $tplCtx['templateId'],
            'user_id' => $otherUserId,
            'stage' => 1,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $headers = $this->authHeaders($otherUserId, ['templates.read']);

        // Can view/index template comments (since they can view the template as a reviewer)
        $response = $this->getJson("/api/v1/templates/{$tplCtx['templateId']}/comments", $headers)
            ->assertOk();
        $this->assertIsArray($response->json('data'));

        // Cannot store template comments (since draft templates are not commenting open to reviewers)
        $this->postJson(
            "/api/v1/templates/{$tplCtx['templateId']}/comments",
            ['body' => 'Intento de comentario por tercero'],
            $headers
        )->assertForbidden();

        // 2. Document: User B has a read-only share on a draft document owned by User A.
        $docCtx = $this->seedDraftDocumentWithBlocks();
        DB::table('document_shares')->insert([
            'id' => (string) Str::uuid(),
            'document_id' => $docCtx['documentId'],
            'user_id' => $otherUserId,
            'permission' => 'read',
            'granted_by' => $docCtx['ownerId'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $docHeaders = $this->authHeaders($otherUserId, ['documents.read']);

        // Can view/index document comments (since they have a read share)
        $docResponse = $this->getJson("/api/v1/documents/{$docCtx['documentId']}/comments", $docHeaders)
            ->assertOk();
        $this->assertIsArray($docResponse->json('data'));

        // Cannot store document comments (since they only have read access)
        $this->postJson(
            "/api/v1/documents/{$docCtx['documentId']}/comments",
            ['body' => 'Intento de comentario en doc por tercero'],
            $docHeaders
        )->assertForbidden();
    }

    // ─── store — template — basic happy path ──────────────────────────────────

    public function test_store_template_comment_requires_authentication(): void
    {
        $this->postJson('/api/v1/templates/'.Str::uuid().'/comments', ['body' => 'Hola'])
            ->assertUnauthorized();
    }

    public function test_store_template_comment_happy_path_without_block(): void
    {
        $ctx     = $this->seedDraftTemplateWithBlock();
        $headers = $this->authHeaders($ctx['ownerId']);

        $response = $this->postJson(
            "/api/v1/templates/{$ctx['templateId']}/comments",
            ['body' => 'Comentario válido'],
            $headers,
        )->assertCreated();

        $data = $response->json('data');
        $this->assertSame('Comentario válido', $data['body']);
        $this->assertSame($ctx['ownerId'], $data['author_id']);
    }

    // ─── store — [A] wrong blockable_type for template ────────────────────────

    /**
     * Passing a blockable_id whose "type" is inferred as DocumentBlock::class for
     * a template comment triggers assertBlockBelongsToResource → wrong type → 422.
     *
     * Since the controller derives blockable_type from the resource class
     * (CommentableResource::blockableClass), for a template resource it always sets
     * TemplateBlock::class — so this branch (wrong type) can only be reached by
     * sending a blockable_id that doesn't exist in template_blocks for this template.
     * That hits the "block not in template" branch [B] instead.
     *
     * The "wrong blockable_type" branch [A] is exercised by the DocumentBlock path:
     * a DocumentBlock blockable_id sent to a template comment endpoint cannot be
     * matched as TemplateBlock — the block won't be found in template_blocks → [B] fires.
     *
     * Test [A/B combined]: unknown blockable_id for a template comment → 422.
     */
    public function test_store_template_comment_with_unknown_block_id_returns_422(): void
    {
        $ctx     = $this->seedDraftTemplateWithBlock();
        $headers = $this->authHeaders($ctx['ownerId']);

        // blockable_id is a valid UUID but doesn't belong to this template
        $foreignBlockId = (string) Str::uuid();

        $this->postJson(
            "/api/v1/templates/{$ctx['templateId']}/comments",
            ['body' => 'Comentario con bloque foráneo', 'blockable_id' => $foreignBlockId],
            $headers,
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['blockable_id']);
    }

    // ─── store — [B] block not belonging to template ──────────────────────────

    public function test_store_template_comment_with_block_from_other_template_returns_422(): void
    {
        $ctx1    = $this->seedDraftTemplateWithBlock(); // owns this template
        $ctx2    = $this->seedDraftTemplateNoBlocks();  // another template (no block needed)

        // Create a block that belongs to a DIFFERENT template than ctx1
        $foreignBlockId = (string) Str::uuid();
        TemplateBlock::query()->forceCreate([
            'id'              => $foreignBlockId,
            'template_id'     => $ctx2['templateId'],
            'title'           => 'Bloque de otra plantilla',
            'default_content' => null,
            'block_state'     => BlockState::Editable->value,
            'sort_order'      => 0,
        ]);

        // ctx1 owns BOTH templates — so the second template is accessible too —
        // but we comment on ctx1 with a block that belongs to ctx2
        $headers = $this->authHeaders($ctx1['ownerId']);

        $this->postJson(
            "/api/v1/templates/{$ctx1['templateId']}/comments",
            ['body' => 'Comentario con bloque de otra plantilla', 'blockable_id' => $foreignBlockId],
            $headers,
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['blockable_id']);
    }

    // ─── store — [D] block not belonging to document ──────────────────────────

    public function test_store_document_comment_with_block_from_other_document_returns_422(): void
    {
        $ctx1    = $this->seedDraftDocumentWithBlocks();
        $ctx2    = $this->seedDraftDocumentWithBlocks();

        // Comment on ctx1's document but pass ctx2's document block
        $headers = $this->authHeaders($ctx1['ownerId']);

        $this->postJson(
            "/api/v1/documents/{$ctx1['documentId']}/comments",
            [
                'body'         => 'Bloque de otro documento',
                'blockable_id' => $ctx2['editableBlockId'],
            ],
            $headers,
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['blockable_id']);
    }

    // ─── store — [E] happy path with valid template block ─────────────────────

    public function test_store_template_comment_with_valid_block_returns_201(): void
    {
        $ctx     = $this->seedDraftTemplateWithBlock();
        $headers = $this->authHeaders($ctx['ownerId']);

        $response = $this->postJson(
            "/api/v1/templates/{$ctx['templateId']}/comments",
            ['body' => 'Comentario en bloque', 'blockable_id' => $ctx['blockId']],
            $headers,
        )->assertCreated();

        $data = $response->json('data');
        $this->assertSame('Comentario en bloque', $data['body']);
        $this->assertSame($ctx['blockId'], $data['blockable_id']);
    }

    // ─── store — [F] happy path with valid document block ─────────────────────

    public function test_store_document_comment_with_valid_block_returns_201(): void
    {
        $ctx     = $this->seedDraftDocumentWithBlocks();
        $headers = $this->authHeaders($ctx['ownerId']);

        $response = $this->postJson(
            "/api/v1/documents/{$ctx['documentId']}/comments",
            ['body' => 'Comentario en bloque documento', 'blockable_id' => $ctx['editableBlockId']],
            $headers,
        )->assertCreated();

        $data = $response->json('data');
        $this->assertSame('Comentario en bloque documento', $data['body']);
        $this->assertSame($ctx['editableBlockId'], $data['blockable_id']);
    }

    // ─── store — [G] parent deleted → 422 ────────────────────────────────────

    public function test_store_template_comment_with_deleted_parent_returns_422(): void
    {
        $ctx     = $this->seedDraftTemplateWithBlock();
        $headers = $this->authHeaders($ctx['ownerId']);

        // Insert a real comment so parent_id passes FormRequest validation
        // (exists:comments,id,deleted_at,NULL fails for soft-deleted rows)
        // We insert it non-deleted, create the child, then this test can't directly
        // test the "deleted" branch via FormRequest — because the FormRequest rule
        // already blocks sending a deleted parent_id.
        //
        // To hit CommentService::assertParentBelongsToResource's deleted branch we
        // need to: (1) create a non-deleted parent comment, (2) soft-delete it after
        // the fact, (3) bypass FormRequest by passing the ID manually — but the route
        // runs FormRequest validation first so it will be blocked at 422 anyway.
        //
        // What we CAN test is the FormRequest validation itself: a soft-deleted comment
        // ID is rejected by the `exists:comments,id,deleted_at,NULL` rule.
        $parentId = $this->insertComment([
            'commentable_type'    => Template::class,
            'commentable_id'      => $ctx['templateId'],
            'commentable_version' => 1,
            'author_id'           => $ctx['ownerId'],
            'deleted_at'          => now(), // already soft-deleted
        ]);

        $this->postJson(
            "/api/v1/templates/{$ctx['templateId']}/comments",
            ['body' => 'Respuesta a padre eliminado', 'parent_id' => $parentId],
            $headers,
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id']);
    }

    // ─── store — [H] parent belongs to different resource → 422 ─────────────

    public function test_store_template_comment_with_parent_from_different_resource_returns_422(): void
    {
        $ctx1    = $this->seedDraftTemplateWithBlock();
        $ctx2    = $this->seedDraftTemplateNoBlocks();

        // Parent lives on ctx2's template
        $parentId = $this->insertComment([
            'commentable_type'    => Template::class,
            'commentable_id'      => $ctx2['templateId'],
            'commentable_version' => 1,
            'author_id'           => $ctx2['ownerId'],
            'deleted_at'          => null,
        ]);

        // Comment on ctx1's template, passing parent from ctx2
        $headers = $this->authHeaders($ctx1['ownerId']);

        $this->postJson(
            "/api/v1/templates/{$ctx1['templateId']}/comments",
            ['body' => 'Respuesta a padre de otra plantilla', 'parent_id' => $parentId],
            $headers,
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id']);
    }

    // ─── store — [I] parent belongs to different block → 422 ─────────────────

    public function test_store_template_comment_with_parent_on_different_block_returns_422(): void
    {
        $ctx     = $this->seedDraftTemplateWithBlock();

        // Create a second block on the same template
        $secondBlockId = (string) Str::uuid();
        TemplateBlock::query()->forceCreate([
            'id'              => $secondBlockId,
            'template_id'     => $ctx['templateId'],
            'title'           => 'Segundo Bloque',
            'default_content' => null,
            'block_state'     => BlockState::Editable->value,
            'sort_order'      => 1,
        ]);

        // Parent comment is on secondBlock
        $parentId = $this->insertComment([
            'commentable_type'    => Template::class,
            'commentable_id'      => $ctx['templateId'],
            'commentable_version' => 1,
            'author_id'           => $ctx['ownerId'],
            'blockable_type'      => TemplateBlock::class,
            'blockable_id'        => $secondBlockId,
            'deleted_at'          => null,
        ]);

        // Reply targets firstBlock — mismatch with parent's block → 422
        $headers = $this->authHeaders($ctx['ownerId']);

        $this->postJson(
            "/api/v1/templates/{$ctx['templateId']}/comments",
            [
                'body'         => 'Respuesta en bloque distinto',
                'parent_id'    => $parentId,
                'blockable_id' => $ctx['blockId'], // different from parent's block
            ],
            $headers,
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id']);
    }

    // ─── store — [J] happy path with valid parent (same resource/block) ───────

    public function test_store_template_comment_reply_to_valid_parent_returns_201(): void
    {
        $ctx     = $this->seedDraftTemplateWithBlock();

        // Insert a non-deleted parent comment on the same template/version/block
        $parentId = $this->insertComment([
            'commentable_type'    => Template::class,
            'commentable_id'      => $ctx['templateId'],
            'commentable_version' => 1,
            'author_id'           => $ctx['ownerId'],
            'blockable_type'      => TemplateBlock::class,
            'blockable_id'        => $ctx['blockId'],
            'deleted_at'          => null,
        ]);

        $headers = $this->authHeaders($ctx['ownerId']);

        $response = $this->postJson(
            "/api/v1/templates/{$ctx['templateId']}/comments",
            [
                'body'         => 'Respuesta al padre',
                'parent_id'    => $parentId,
                'blockable_id' => $ctx['blockId'],
            ],
            $headers,
        )->assertCreated();

        $data = $response->json('data');
        $this->assertSame('Respuesta al padre', $data['body']);
        $this->assertSame($parentId, $data['parent_id']);
    }

    // ─── show — authentication ────────────────────────────────────────────────

    public function test_show_requires_authentication(): void
    {
        $this->getJson('/api/v1/comments/'.Str::uuid())
            ->assertUnauthorized();
    }

    public function test_show_returns_404_for_unknown_comment(): void
    {
        $userId  = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $this->getJson('/api/v1/comments/'.Str::uuid(), $headers)
            ->assertNotFound();
    }

    public function test_show_returns_comment_for_author(): void
    {
        $ctx      = $this->seedDraftTemplateWithBlock();
        $headers  = $this->authHeaders($ctx['ownerId']);

        // First create a comment via the API
        $createResponse = $this->postJson(
            "/api/v1/templates/{$ctx['templateId']}/comments",
            ['body' => 'Comentario para show'],
            $headers,
        )->assertCreated();

        $commentId = $createResponse->json('data.id');

        $response = $this->getJson("/api/v1/comments/{$commentId}", $headers)
            ->assertOk();

        $this->assertSame($commentId, $response->json('data.id'));
        $this->assertSame('Comentario para show', $response->json('data.body'));
    }

    // ─── destroy — authentication ─────────────────────────────────────────────

    public function test_destroy_requires_authentication(): void
    {
        $this->deleteJson('/api/v1/comments/'.Str::uuid())
            ->assertUnauthorized();
    }

    public function test_destroy_returns_404_for_unknown_comment(): void
    {
        $userId  = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        $this->deleteJson('/api/v1/comments/'.Str::uuid(), [], $headers)
            ->assertNotFound();
    }

    public function test_destroy_soft_deletes_own_template_comment(): void
    {
        $ctx      = $this->seedDraftTemplateWithBlock();
        $headers  = $this->authHeaders($ctx['ownerId']);

        // Create via API
        $createResponse = $this->postJson(
            "/api/v1/templates/{$ctx['templateId']}/comments",
            ['body' => 'Comentario a eliminar'],
            $headers,
        )->assertCreated();

        $commentId = $createResponse->json('data.id');

        $this->deleteJson("/api/v1/comments/{$commentId}", [], $headers)
            ->assertNoContent();

        $this->assertSoftDeleted('comments', ['id' => $commentId]);
    }

    // ─── store — validation errors ────────────────────────────────────────────

    public function test_store_template_comment_rejects_empty_body(): void
    {
        $ctx     = $this->seedDraftTemplateWithBlock();
        $headers = $this->authHeaders($ctx['ownerId']);

        $this->postJson(
            "/api/v1/templates/{$ctx['templateId']}/comments",
            ['body' => ''],
            $headers,
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
    }

    public function test_store_template_comment_rejects_legacy_block_field(): void
    {
        $ctx     = $this->seedDraftTemplateWithBlock();
        $headers = $this->authHeaders($ctx['ownerId']);

        $this->postJson(
            "/api/v1/templates/{$ctx['templateId']}/comments",
            ['body' => 'Cuerpo válido', 'template_block_id' => $ctx['blockId']],
            $headers,
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['blockable_id']);
    }
}
