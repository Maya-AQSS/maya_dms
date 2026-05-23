<?php

declare(strict_types=1);

namespace Tests\Feature\TemplateBlocks;

use App\Enums\BlockKind;
use App\Enums\TemplateVisibilityLevel;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Models\TemplateVersionBlockLayer;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\UserPermissionsSeeder;
use Database\Seeders\UsersSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Lcobucci\JWT\Signer\Key\InMemory;
use Maya\Auth\Contracts\JwksServiceInterface;
use Tests\Concerns\AssignsTestUserPermissions;
use Tests\Concerns\BuildsTestJwt;
use Tests\TestCase;

class KindPersistsInVersioningTest extends TestCase
{
    use AssignsTestUserPermissions;
    use BuildsTestJwt;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth.jwt_issuer' => 'test-issuer',
            'auth.jwt_audience' => 'test-audience',
        ]);

        Cache::flush();

        $this->seed(UsersSourceSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(UserPermissionsSeeder::class);
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(string $sub): array
    {
        auth()->forgetUser();

        $this->assignUserPermissions($sub, ['template.show', 'template.create_block', 'template.publish']);

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

    private function makePersonalDraftTemplate(string $creatorId): string
    {
        $tid = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $tid,
            'name' => 'Plantilla versioning test',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $creatorId,
            'status' => 'draft',
            'review_stages' => 0,
            'review_mode' => 'parallel',
        ]);

        return $tid;
    }

    public function test_kind_persists_through_template_publication(): void
    {
        $creatorId = (string) Str::uuid();
        $headers = $this->authHeaders($creatorId);
        $tid = $this->makePersonalDraftTemplate($creatorId);

        // Create blocks with different kinds
        $blockIds = [];
        foreach ([
            ['kind' => BlockKind::Cover->value, 'title' => 'Cover'],
            ['kind' => BlockKind::Content->value, 'title' => 'Content'],
            ['kind' => BlockKind::Blank->value, 'title' => 'Blank'],
            ['kind' => BlockKind::Toc->value, 'title' => 'TOC'],
        ] as $idx => $block) {
            $response = $this->postJson("/api/v1/templates/{$tid}/blocks", [
                'title' => $block['title'],
                'kind' => $block['kind'],
                'default_content' => $block['kind'] === BlockKind::Blank->value ? [] : ['text' => 'content'],
                'block_state' => $block['kind'] === BlockKind::Toc->value ? 'locked' : 'editable',
                'sort_order' => $idx,
            ], $headers);

            $blockIds[] = $response->json('data.id');
        }

        // Publish the template
        $publishResponse = $this->postJson("/api/v1/templates/{$tid}/publish", [], $headers);
        $publishResponse->assertOk();

        // Verify blocks maintain their kind in database
        foreach ($blockIds as $idx => $blockId) {
            $expected = [
                BlockKind::Cover->value,
                BlockKind::Content->value,
                BlockKind::Blank->value,
                BlockKind::Toc->value,
            ][$idx];

            $block = TemplateBlock::query()->findOrFail($blockId);
            $this->assertEquals($expected, $block->kind);
        }
    }

    public function test_kind_included_in_override_payload_on_publication(): void
    {
        $creatorId = (string) Str::uuid();
        $headers = $this->authHeaders($creatorId);
        $tid = $this->makePersonalDraftTemplate($creatorId);

        // Create a content block with kind
        $blockResponse = $this->postJson("/api/v1/templates/{$tid}/blocks", [
            'title' => 'Test Block',
            'kind' => BlockKind::Content->value,
            'default_content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Test']]],
            ],
            'block_state' => 'editable',
            'sort_order' => 0,
        ], $headers);

        $blockId = $blockResponse->json('data.id');

        // Publish
        $this->postJson("/api/v1/templates/{$tid}/publish", [], $headers)->assertOk();

        // Check that override_payload includes kind
        $layer = TemplateVersionBlockLayer::query()
            ->where('template_block_id', $blockId)
            ->first();

        $this->assertNotNull($layer);
        $payload = is_array($layer->override_payload) ? $layer->override_payload : [];
        $this->assertArrayHasKey('kind', $payload);
        $this->assertEquals(BlockKind::Content->value, $payload['kind']);
    }
}
