<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentShare;
use App\Models\Template;
use Maya\Auth\Contracts\JwksServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lcobucci\JWT\Signer\Key\InMemory;
use Tests\Concerns\BuildsTestJwt;
use Tests\TestCase;

/**
 * Requiere PostgreSQL (o motor compatible con gen_random_uuid() en migraciones).
 * Con SQLite en phpunit.xml las migraciones fallan igual que en AuditLogInsertLatencyTest.
 */
class DocumentSoDHttpAcceptanceTest extends TestCase
{
    use BuildsTestJwt;
    use RefreshDatabase;

    private function seedTemplateAndDocument(string $creatorId): array
    {
        $templateId = (string) Str::uuid();
        $documentId = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $templateId,
            'name' => 'Plantilla SoD',
            'description' => null,
            'study_id' => null,
            'created_by' => $creatorId,
            'status' => 'draft',
            'version' => 1,
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        Document::query()->forceCreate([
            'id' => $documentId,
            'template_id' => $templateId,
            'title' => 'Documento SoD',
            'study_id' => null,
            'created_by' => $creatorId,
            'owner_id' => $creatorId,
            'status' => 'draft',
            'current_version' => 1,
            'submitted_at' => null,
            'published_at' => null,
        ]);

        return [$templateId, $documentId];
    }

    /**
     * El titular puede enviar a revisión su documento (HTTP 200).
     */
    public function test_owner_submit_own_document_returns_ok(): void
    {
        [$templateId, $documentId] = $this->seedTemplateAndDocument('creator-doc-uuid-01');

        [$privatePem, $publicPem] = $this->generateRsaKeyPairForTests();
        $token = $this->buildJwtForSub($privatePem, $publicPem, 'kid-sod-doc', 'creator-doc-uuid-01');

        config([
            'auth.jwt_issuer' => 'test-issuer',
            'auth.jwt_audience' => 'test-audience',
        ]);

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn(InMemory::plainText($publicPem));

        $this->postJson(
            "/api/v1/documents/{$documentId}/submit",
            [],
            ['Authorization' => 'Bearer '.$token],
        )->assertOk()
            ->assertJsonPath('data.status', 'in_review');
    }

    /**
     * Usuario compartido (no titular) no puede enviar a revisión → 403 y audit_log.
     */
    public function test_shared_user_submit_returns_403_and_writes_audit_log(): void
    {
        $ownerId = 'owner-sod-share-01';
        $sharedId = 'shared-sod-share-02';
        $templateId = (string) Str::uuid();
        $documentId = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $templateId,
            'name' => 'T',
            'description' => null,
            'study_id' => null,
            'created_by' => $ownerId,
            'status' => 'draft',
            'version' => 1,
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        Document::query()->forceCreate([
            'id' => $documentId,
            'template_id' => $templateId,
            'title' => 'Doc compartido',
            'study_id' => null,
            'created_by' => $ownerId,
            'owner_id' => $ownerId,
            'status' => 'draft',
            'current_version' => 1,
            'submitted_at' => null,
            'published_at' => null,
        ]);

        DocumentShare::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'document_id' => $documentId,
            'user_id' => $sharedId,
            'permission' => 'read',
            'granted_by' => $ownerId,
        ]);

        [$privatePem, $publicPem] = $this->generateRsaKeyPairForTests();
        $token = $this->buildJwtForSub($privatePem, $publicPem, 'kid-sod-sh', $sharedId);

        config([
            'auth.jwt_issuer' => 'test-issuer',
            'auth.jwt_audience' => 'test-audience',
        ]);

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn(InMemory::plainText($publicPem));

        $this->postJson(
            "/api/v1/documents/{$documentId}/submit",
            [],
            ['Authorization' => 'Bearer '.$token],
        )->assertForbidden();

        $this->assertDatabaseHas('audit_log', [
            'entity_type' => 'document',
            'entity_id' => $documentId,
            'action' => 'sod_violation',
            'user_id' => $sharedId,
        ]);

        $row = DB::table('audit_log')
            ->where('entity_id', $documentId)
            ->where('action', 'sod_violation')
            ->first();

        $this->assertNotNull($row);
        $newValue = is_string($row->new_value) ? json_decode($row->new_value, true) : $row->new_value;
        $this->assertIsArray($newValue);
        $this->assertSame('WARNING', $newValue['level'] ?? null);
        $this->assertArrayHasKey('ability', $newValue);
        $this->assertNotEmpty($row->timestamp);
    }

    /**
     * Escenario 2: creador publica plantilla (revisión/aprobación) → HTTP 403.
     */
    public function test_creator_publish_template_returns_403(): void
    {
        $creatorId = 'creator-tpl-uuid-02';
        [$templateId] = $this->seedTemplateAndDocument($creatorId);

        [$privatePem, $publicPem] = $this->generateRsaKeyPairForTests();
        $token = $this->buildJwtForSub($privatePem, $publicPem, 'kid-sod-tpl', $creatorId);

        config([
            'auth.jwt_issuer' => 'test-issuer',
            'auth.jwt_audience' => 'test-audience',
        ]);

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn(InMemory::plainText($publicPem));

        $this->postJson(
            "/api/v1/templates/{$templateId}/publish",
            ['changelog' => 'Versión inicial'],
            ['Authorization' => 'Bearer '.$token],
        )->assertForbidden();
    }

    /**
     * Tras delegación, el nuevo titular puede enviar a revisión.
     */
    public function test_delegate_owner_can_submit_document(): void
    {
        $creatorId = 'creator-deleg-uuid-03';
        $ownerId = 'owner-deleg-uuid-04';

        $templateId = (string) Str::uuid();
        $documentId = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $templateId,
            'name' => 'T',
            'description' => null,
            'study_id' => null,
            'created_by' => $creatorId,
            'status' => 'draft',
            'version' => 1,
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        Document::query()->forceCreate([
            'id' => $documentId,
            'template_id' => $templateId,
            'title' => 'Doc delegado',
            'study_id' => null,
            'created_by' => $creatorId,
            'owner_id' => $ownerId,
            'status' => 'draft',
            'current_version' => 1,
            'submitted_at' => null,
            'published_at' => null,
        ]);

        [$privatePem, $publicPem] = $this->generateRsaKeyPairForTests();
        $token = $this->buildJwtForSub($privatePem, $publicPem, 'kid-sod-del', $ownerId);

        config([
            'auth.jwt_issuer' => 'test-issuer',
            'auth.jwt_audience' => 'test-audience',
        ]);

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn(InMemory::plainText($publicPem));

        $this->postJson(
            "/api/v1/documents/{$documentId}/submit",
            [],
            ['Authorization' => 'Bearer '.$token],
        )->assertOk()
            ->assertJsonPath('data.status', 'in_review');
    }
}
