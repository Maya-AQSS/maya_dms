<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BlockState;
use App\Enums\TemplateVisibilityLevel;
use App\Models\Document;
use App\Models\DocumentBlock;
use App\Models\Template;
use App\Models\TemplateBlock;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\UserPermissionsSeeder;
use Database\Seeders\UsersSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Maya\Auth\Contracts\JwksServiceInterface;
use Tests\Concerns\AssignsTestUserPermissions;
use Tests\Concerns\BuildsTestJwt;
use Tests\Concerns\SeedsTemplatePublicationAnchor;
use Tests\TestCase;

/**
 * Tests for DocumentBlockService edge-case paths not covered by DocumentBlockApiTest.
 *
 * Targets (72.03% → ≥80%):
 *   - updateBlock: document not in draft/rejected → AuthorizationException (403)
 *   - updateBlock: locked block_state → AuthorizationException (403)
 *   - updateBlock: content unchanged → early return (200 with existing content)
 *   - deleteOptionalBlock: document not in draft/rejected → AuthorizationException (403)
 *   - deleteOptionalBlock: block is editable (not optional) → AuthorizationException (403)
 */
class DocumentBlockServiceEdgeCasesApiTest extends TestCase
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
        array $codes = ['documents.read', 'documents.update', 'templates.read'],
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
     * Seeds a document with the given status and a block with the given block_state.
     *
     * @return array{ownerId: string, documentId: string, blockId: string}
     */
    private function seedDocumentWithBlock(
        string $status,
        string $blockState = 'editable',
        mixed $blockContent = null,
    ): array {
        $ownerId    = (string) Str::uuid();
        $templateId = (string) Str::uuid();
        $tplBlockId = (string) Str::uuid();
        $documentId = (string) Str::uuid();
        $blockId    = (string) Str::uuid();

        Template::query()->forceCreate([
            'id'               => $templateId,
            'process_id'       => '00000000-0000-0000-0000-000000000001',
            'name'             => 'Plantilla Edge Cases',
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
            'title'           => 'Bloque Edge Case',
            'default_content' => null,
            'block_state'     => $blockState,
            'sort_order'      => 0,
        ]);

        // We also need an editable block so the invariant check (≥1 editable block) passes
        // only when we're seeding an optional/locked block — add a second editable block
        if ($blockState !== 'editable') {
            $editableTplBlockId = (string) Str::uuid();
            TemplateBlock::query()->forceCreate([
                'id'              => $editableTplBlockId,
                'template_id'     => $templateId,
                'title'           => 'Bloque Editable Requerido',
                'default_content' => null,
                'block_state'     => 'editable',
                'sort_order'      => 1,
            ]);
        }

        $anchor = $this->seedCanonicalPublicationForTemplate(
            $templateId,
            1,
            $ownerId,
            [
                [
                    'id'              => $tplBlockId,
                    'title'           => 'Bloque Edge Case',
                    'default_content' => null,
                    'block_state'     => $blockState,
                    'sort_order'      => 0,
                    'type'            => '',
                    'mandatory'       => $blockState === 'editable',
                ],
            ],
        );

        Document::query()->forceCreate([
            'id'                  => $documentId,
            'process_id'          => '00000000-0000-0000-0000-000000000001',
            'template_id'         => $templateId,
            'template_version_id' => $anchor['entity_version_id'],
            'title'               => 'Documento Edge Cases',
            'created_by'          => $ownerId,
            'owner_id'            => $ownerId,
            'status'              => $status,
        ]);

        DocumentBlock::query()->forceCreate([
            'id'                => $blockId,
            'document_id'       => $documentId,
            'template_block_id' => $tplBlockId,
            'content'           => $blockContent,
            'is_filled'         => $blockContent !== null,
            'sort_order'        => 0,
        ]);

        return [
            'ownerId'    => $ownerId,
            'documentId' => $documentId,
            'blockId'    => $blockId,
        ];
    }

    // ─── updateBlock — document not in draft/rejected ─────────────────────────

    public function test_update_block_returns_403_when_document_is_in_review(): void
    {
        $ctx     = $this->seedDocumentWithBlock('in_review');
        $headers = $this->authHeaders($ctx['ownerId']);

        $this->putJson(
            "/api/v1/documents/{$ctx['documentId']}/blocks/{$ctx['blockId']}",
            ['content' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]]],
            $headers,
        )->assertForbidden();
    }

    public function test_update_block_returns_403_when_document_is_published(): void
    {
        $ctx     = $this->seedDocumentWithBlock('published');
        $headers = $this->authHeaders($ctx['ownerId']);

        $this->putJson(
            "/api/v1/documents/{$ctx['documentId']}/blocks/{$ctx['blockId']}",
            ['content' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]]],
            $headers,
        )->assertForbidden();
    }

    // ─── updateBlock — locked block ───────────────────────────────────────────

    public function test_update_block_returns_403_when_block_state_is_locked(): void
    {
        $ctx     = $this->seedDocumentWithBlock('draft', BlockState::Locked->value);
        $headers = $this->authHeaders($ctx['ownerId']);

        $this->putJson(
            "/api/v1/documents/{$ctx['documentId']}/blocks/{$ctx['blockId']}",
            ['content' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]]],
            $headers,
        )->assertForbidden();
    }

    // ─── updateBlock — content unchanged (early return) ──────────────────────

    public function test_update_block_returns_200_when_content_is_unchanged(): void
    {
        $existingContent = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'text' => 'original']]];
        $ctx             = $this->seedDocumentWithBlock('draft', 'editable', $existingContent);
        $headers         = $this->authHeaders($ctx['ownerId']);

        // Send the exact same content — service returns early without updating
        $response = $this->putJson(
            "/api/v1/documents/{$ctx['documentId']}/blocks/{$ctx['blockId']}",
            ['content' => $existingContent],
            $headers,
        )->assertOk();

        $data = $response->json('data');
        $this->assertSame($ctx['blockId'], $data['document_block_id']);
        // is_filled should be true since existing content was set
        $this->assertTrue($data['is_filled']);
    }

    // ─── deleteOptionalBlock — document not in draft/rejected ─────────────────

    public function test_destroy_optional_block_returns_403_when_document_is_in_review(): void
    {
        $ctx     = $this->seedDocumentWithBlock('in_review', BlockState::Optional->value);
        $headers = $this->authHeaders($ctx['ownerId']);

        $this->deleteJson(
            "/api/v1/documents/{$ctx['documentId']}/blocks/{$ctx['blockId']}",
            [],
            $headers,
        )->assertForbidden();
    }

    public function test_destroy_optional_block_returns_403_when_document_is_published(): void
    {
        $ctx     = $this->seedDocumentWithBlock('published', BlockState::Optional->value);
        $headers = $this->authHeaders($ctx['ownerId']);

        $this->deleteJson(
            "/api/v1/documents/{$ctx['documentId']}/blocks/{$ctx['blockId']}",
            [],
            $headers,
        )->assertForbidden();
    }

    // ─── deleteOptionalBlock — block is editable (not optional) ──────────────

    public function test_destroy_editable_block_returns_403(): void
    {
        $ctx     = $this->seedDocumentWithBlock('draft', BlockState::Editable->value);
        $headers = $this->authHeaders($ctx['ownerId']);

        $this->deleteJson(
            "/api/v1/documents/{$ctx['documentId']}/blocks/{$ctx['blockId']}",
            [],
            $headers,
        )->assertForbidden();
    }

    // ─── deleteOptionalBlock — happy path for rejected document ──────────────

    public function test_destroy_optional_block_succeeds_when_document_is_rejected(): void
    {
        $ctx     = $this->seedDocumentWithBlock('rejected', BlockState::Optional->value);
        $headers = $this->authHeaders($ctx['ownerId']);

        $this->deleteJson(
            "/api/v1/documents/{$ctx['documentId']}/blocks/{$ctx['blockId']}",
            [],
            $headers,
        )->assertNoContent();

        $this->assertSoftDeleted('document_blocks', ['id' => $ctx['blockId']]);
    }

    // ─── updateBlock — happy path for rejected document ───────────────────────

    public function test_update_block_succeeds_when_document_is_rejected(): void
    {
        $ctx     = $this->seedDocumentWithBlock('rejected');
        $headers = $this->authHeaders($ctx['ownerId']);

        $newContent = ['type' => 'doc', 'content' => [['type' => 'paragraph']]];

        $response = $this->putJson(
            "/api/v1/documents/{$ctx['documentId']}/blocks/{$ctx['blockId']}",
            ['content' => $newContent],
            $headers,
        )->assertOk();

        $data = $response->json('data');
        $this->assertSame($ctx['blockId'], $data['document_block_id']);
        $this->assertTrue($data['is_filled']);
    }
}
