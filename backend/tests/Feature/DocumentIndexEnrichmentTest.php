<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TemplateVisibilityLevel;
use App\Models\Document;
use App\Models\EntityVersion;
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
 * Integration tests for DocumentController::index() enrichment pipeline:
 * attachLatestPublishedVersionMeta, attachTemplateVersionNumbers, attachShareMetadataForViewer.
 */
class DocumentIndexEnrichmentTest extends TestCase
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

    /** @return array<string, string> */
    private function authHeaders(string $sub, array $permissions = ['document.show', 'template.show']): array
    {
        auth()->forgetUser();

        $this->assignUserPermissions($sub, $permissions);

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

    /** Seed a minimal template + published EntityVersion, returning their IDs. */
    private function seedPublishedTemplate(string $userId): array
    {
        $templateId = (string) Str::uuid();
        $blockId    = (string) Str::uuid();

        Template::query()->forceCreate([
            'id'               => $templateId,
            'name'             => 'Plantilla enriquecimiento',
            'description'      => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'study_type_id'    => null,
            'study_id'         => null,
            'module_id'        => null,
            'team_id'          => null,
            'created_by'       => $userId,
            'status'           => 'published',
            'review_stages'    => 0,
            'review_mode'      => 'sequential',
        ]);

        TemplateBlock::query()->forceCreate([
            'id'              => $blockId,
            'template_id'     => $templateId,
            'title'           => 'Bloque 1',
            'default_content' => null,
            'block_state'     => 'optional',
            'sort_order'      => 0,
        ]);

        $result = $this->seedCanonicalPublicationForTemplate(
            $templateId,
            1,
            $userId,
            [['id' => $blockId, 'title' => 'Bloque 1', 'default_content' => null, 'sort_order' => 0]],
            ['template' => ['visibility_level' => 'personal']],
        );

        return ['template_id' => $templateId, 'template_version_id' => $result['entity_version_id']];
    }

    /**
     * Seed a Document (model observer auto-creates head EntityVersion at version_number=0)
     * + a canonical published EntityVersion (version_number=1).
     */
    private function seedDocumentWithPublishedVersion(string $userId, string $templateId, string $templateVersionId, string $publishedTitle = 'Título publicado'): array
    {
        $documentId = (string) Str::uuid();
        $now = now();

        Document::query()->forceCreate([
            'id'                  => $documentId,
            'template_id'         => $templateId,
            'template_version_id' => $templateVersionId,
            'title'               => 'Doc borrador',
            'study_type_id'       => null,
            'study_id'            => null,
            'module_id'           => null,
            'team_id'             => null,
            'delivery_deadline'   => $now->addMonths(1)->format('Y-m-d'),
            'created_by'          => $userId,
            'owner_id'            => $userId,
            'status'              => 'published',
        ]);

        $publishedEvId = (string) Str::uuid();

        // Canonical published snapshot (version_number=1). Head (version_number=0) was auto-created by the Document model observer.
        DB::table('entity_versions')->insert([
            'id'                   => $publishedEvId,
            'versionable_type'     => Document::class,
            'versionable_id'       => $documentId,
            'version_number'       => 1,
            'status'               => 'published',
            'created_by'           => $userId,
            'published_by'         => $userId,
            'published_at'         => $now,
            'changelog'            => 'v1',
            'snapshot_data'        => json_encode(['document' => ['title' => $publishedTitle], 'blocks' => []]),
            'is_snapshot_immutable'=> true,
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);

        return ['document_id' => $documentId, 'published_ev_id' => $publishedEvId];
    }

    public function test_index_attaches_latest_published_version_meta(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        ['template_id' => $templateId, 'template_version_id' => $templateVersionId] = $this->seedPublishedTemplate($userId);
        ['document_id' => $documentId, 'published_ev_id' => $publishedEvId] = $this->seedDocumentWithPublishedVersion(
            $userId,
            $templateId,
            $templateVersionId,
            'Mi título publicado',
        );

        $response = $this->getJson('/api/v1/documents', $headers);

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $documentId);
        $response->assertJsonPath('data.0.latest_published_version_id', $publishedEvId);
        $response->assertJsonPath('data.0.latest_published_version_number', 1);
        $response->assertJsonPath('data.0.latest_published_title', 'Mi título publicado');
    }

    public function test_index_attaches_template_version_number(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        ['template_id' => $templateId, 'template_version_id' => $templateVersionId] = $this->seedPublishedTemplate($userId);
        $this->seedDocumentWithPublishedVersion($userId, $templateId, $templateVersionId);

        $response = $this->getJson('/api/v1/documents', $headers);

        $response->assertOk();
        $response->assertJsonPath('data.0.template_version_number', 1);
    }

    public function test_index_returns_null_enrichment_for_document_with_no_published_version(): void
    {
        $userId = (string) Str::uuid();
        $headers = $this->authHeaders($userId);

        ['template_id' => $templateId, 'template_version_id' => $templateVersionId] = $this->seedPublishedTemplate($userId);
        $documentId = (string) Str::uuid();
        $now = now();

        // Model observer auto-creates the head EntityVersion (version_number=0).
        // No canonical published version inserted — enrichment should return nulls.
        Document::query()->forceCreate([
            'id'                  => $documentId,
            'template_id'         => $templateId,
            'template_version_id' => $templateVersionId,
            'title'               => 'Doc sin publicar',
            'study_type_id'       => null,
            'study_id'            => null,
            'module_id'           => null,
            'team_id'             => null,
            'delivery_deadline'   => $now->addMonths(1)->format('Y-m-d'),
            'created_by'          => $userId,
            'owner_id'            => $userId,
            'status'              => 'draft',
        ]);

        $response = $this->getJson('/api/v1/documents', $headers);

        $response->assertOk();
        $response->assertJsonPath('data.0.latest_published_version_id', null);
        $response->assertJsonPath('data.0.latest_published_version_number', null);
        $response->assertJsonPath('data.0.latest_published_title', null);
    }

    public function test_index_viewer_sees_is_shared_flag_when_document_is_shared_with_them(): void
    {
        $ownerId  = (string) Str::uuid();
        $viewerId = (string) Str::uuid();
        $headers  = $this->authHeaders($viewerId);

        $this->assignUserPermissions($ownerId, ['document.show', 'template.show']);

        ['template_id' => $templateId, 'template_version_id' => $templateVersionId] = $this->seedPublishedTemplate($ownerId);
        ['document_id' => $documentId] = $this->seedDocumentWithPublishedVersion($ownerId, $templateId, $templateVersionId);

        $now = now();
        DB::table('document_shares')->insert([
            'id'          => (string) Str::uuid(),
            'document_id' => $documentId,
            'user_id'     => $viewerId,
            'permission'  => 'view',
            'granted_by'  => $ownerId,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        $response = $this->getJson('/api/v1/documents', $headers);

        $response->assertOk();
        $documents = $response->json('data');
        $doc = collect($documents)->firstWhere('id', $documentId);
        $this->assertNotNull($doc, 'Document not found in index response');
        $this->assertTrue($doc['is_shared_with_me'] ?? false, 'Expected is_shared_with_me to be true');
    }
}
