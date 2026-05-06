<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BlockState;
use App\Enums\TemplateVisibilityLevel;
use App\Models\BlockVersion;
use App\Models\Document;
use App\Models\DocumentBlock;
use App\Models\Template;
use App\Models\TemplateBlock;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\UserPermissionsSeeder;
use Database\Seeders\UsersSourceSeeder;
use Maya\Auth\Contracts\JwksServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Lcobucci\JWT\Signer\Key\InMemory;
use Tests\Concerns\AssignsTestUserPermissions;
use Tests\Concerns\BuildsTestJwt;
use Tests\Concerns\SeedsTemplatePublicationAnchor;
use Tests\TestCase;

/**
 * Bloques en estado modificable: historial append-only en block_versions (línea base de plantilla + cada guardado).
 */
class DocumentModifiableBlockVersionApiTest extends TestCase
{
    use AssignsTestUserPermissions;
    use BuildsTestJwt;
    use RefreshDatabase;
    use SeedsTemplatePublicationAnchor;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth.jwt_issuer' => 'test-issuer',
            'auth.jwt_audience' => 'test-audience',
        ]);

        $this->seed(UsersSourceSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(UserPermissionsSeeder::class);
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(string $sub, array $permissionCodes = ['templates.read', 'documents.read']): array
    {
        auth()->forgetUser();
        $this->assignUserPermissions($sub, $permissionCodes);

        [$privatePem, $publicPem] = $this->generateRsaKeyPairForTests();

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn(InMemory::plainText($publicPem));

        $token = $this->buildJwtForSub(
            $privatePem,
            $publicPem,
            'kid-'.substr($sub, 0, 8),
            $sub,
            'test-issuer',
            'test-audience',
            [],
            [],
        );

        return ['Authorization' => 'Bearer '.$token];
    }

    /**
     * @return array{ownerId: string, documentId: string, blockId: string, baseline: array<string, mixed>}
     */
    private function seedDraftWithModifiableBlock(): array
    {
        $ownerId = (string) Str::uuid();
        $templateId = (string) Str::uuid();
        $blockSnapId = (string) Str::uuid();
        $documentId = (string) Str::uuid();
        $docBlockId = (string) Str::uuid();

        $baseline = [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'BASE', 'styles' => []]]],
            ],
        ];

        Template::query()->forceCreate([
            'id' => $templateId,
            'name' => 'T mod',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $ownerId,
            'status' => 'draft',
            'version' => 1,
            'review_stages' => 0,
            'review_mode' => 'parallel',
        ]);

        TemplateBlock::query()->forceCreate([
            'id' => $blockSnapId,
            'template_id' => $templateId,
            'title' => 'Normativa',
            'default_content' => $baseline,
            'description' => null,
            'block_state' => BlockState::Modifiable->value,
            'sort_order' => 0,
        ]);

        $anchor = $this->seedCanonicalPublicationForTemplate(
            $templateId,
            1,
            $ownerId,
            [[
                'id' => $blockSnapId,
                'title' => 'Normativa',
                'default_content' => $baseline,
                'block_state' => BlockState::Modifiable->value,
                'sort_order' => 0,
                'type' => '',
                'mandatory' => false,
            ]],
        );

        Document::query()->forceCreate([
            'id' => $documentId,
            'process_id' => '00000000-0000-0000-0000-000000000001',
            'template_id' => $templateId,
            'template_version_id' => $anchor['entity_version_id'],
            'title' => 'Doc mod',
            'study_id' => null,
            'created_by' => $ownerId,
            'owner_id' => $ownerId,
            'status' => 'draft',
            'current_version' => 1,
            'submitted_at' => null,
            'published_at' => null,
        ]);

        DocumentBlock::query()->forceCreate([
            'id' => $docBlockId,
            'document_id' => $documentId,
            'template_block_id' => $blockSnapId,
            'content' => $baseline,
            'is_filled' => true,
            'last_edited_by' => null,
            'locked_by' => null,
            'locked_at' => null,
            'sort_order' => 0,
        ]);

        return [
            'ownerId' => $ownerId,
            'documentId' => $documentId,
            'blockId' => $docBlockId,
            'baseline' => $baseline,
        ];
    }

    public function test_first_edit_on_modifiable_block_inserts_baseline_and_new_version(): void
    {
        $ctx = $this->seedDraftWithModifiableBlock();
        $h = $this->authHeaders($ctx['ownerId']);

        $newContent = [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'EDIT1', 'styles' => []]]],
            ],
        ];

        $this->putJson(
            "/api/v1/documents/{$ctx['documentId']}/blocks/{$ctx['blockId']}",
            ['content' => $newContent],
            $h,
        )->assertOk();

        $versions = BlockVersion::query()
            ->where('document_block_id', $ctx['blockId'])
            ->orderBy('version_number')
            ->get();

        $this->assertCount(2, $versions);
        $this->assertSame(1, $versions[0]->version_number);
        $this->assertSame($ctx['ownerId'], $versions[0]->edited_by);
        $this->assertTrue($this->jsonCanonical($versions[0]->content) === $this->jsonCanonical($ctx['baseline']));

        $this->assertSame(2, $versions[1]->version_number);
        $this->assertSame($ctx['ownerId'], $versions[1]->edited_by);
        $this->assertTrue($this->jsonCanonical($versions[1]->content) === $this->jsonCanonical($newContent));
    }

    public function test_second_edit_appends_single_version_row(): void
    {
        $ctx = $this->seedDraftWithModifiableBlock();
        $h = $this->authHeaders($ctx['ownerId']);

        $edit1 = [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'EDIT1', 'styles' => []]]],
            ],
        ];
        $edit2 = [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'EDIT2', 'styles' => []]]],
            ],
        ];

        $this->putJson(
            "/api/v1/documents/{$ctx['documentId']}/blocks/{$ctx['blockId']}",
            ['content' => $edit1],
            $h,
        )->assertOk();

        $this->putJson(
            "/api/v1/documents/{$ctx['documentId']}/blocks/{$ctx['blockId']}",
            ['content' => $edit2],
            $h,
        )->assertOk();

        $this->assertSame(3, BlockVersion::query()->where('document_block_id', $ctx['blockId'])->count());
        $last = BlockVersion::query()
            ->where('document_block_id', $ctx['blockId'])
            ->orderByDesc('version_number')
            ->first();
        $this->assertNotNull($last);
        $this->assertSame(3, $last->version_number);
        $this->assertTrue($this->jsonCanonical($last->content) === $this->jsonCanonical($edit2));
    }

    /**
     * Codifica un valor JSON canónicamente.
     */
    private function jsonCanonical(mixed $data): string
    {
        $normalized = $data === null ? [] : (is_array($data) ? $data : []);

        return json_encode(
            $this->normalizeKeysForCanonicalJson($normalized),
            \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION,
        );
    }

    /**
     * Ordena claves de objetos JSON (arrays asociativos) de forma recursiva; preserva el orden de listas.
     * 
     * @return mixed
     */
    private function normalizeKeysForCanonicalJson(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if ($value === [] || array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->normalizeKeysForCanonicalJson($item), $value);
        }

        ksort($value);

        foreach ($value as $k => $nested) {
            $value[$k] = $this->normalizeKeysForCanonicalJson($nested);
        }

        return $value;
    }
}
