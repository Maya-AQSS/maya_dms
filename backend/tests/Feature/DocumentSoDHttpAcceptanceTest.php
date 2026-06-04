<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentBlock;
use App\Models\DocumentShare;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Models\TemplateReviewer;
use Maya\Auth\Contracts\JwksServiceInterface;
use Maya\Messaging\Publishers\AuditPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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
            'visibility_level' => 'personal',
            'study_id' => null,
            'created_by' => $creatorId,
            'status' => 'draft',
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);
        $templateBlockId = (string) Str::uuid();
        TemplateBlock::query()->forceCreate([
            'id' => $templateBlockId,
            'template_id' => $templateId,
            'title' => 'Bloque SoD',
            'default_content' => ['text' => 'Contenido inicial'],
            'block_state' => 'editable',
            'sort_order' => 0,
        ]);

        Document::query()->forceCreate([
            'id' => $documentId,
            'template_id' => $templateId,
            'title' => 'Documento SoD',
            'study_id' => null,
            'created_by' => $creatorId,
            'owner_id' => $creatorId,
            'status' => 'draft',
        ]);

        // El submit valida que todos los bloques editables tienen contenido. Como el
        // documento se crea aquí sin pasar por la API, sembramos el document_block
        // ya rellenado para que el submit no falle por validación de bloques vacíos.
        DocumentBlock::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'document_id' => $documentId,
            'template_block_id' => $templateBlockId,
            'content' => ['text' => 'Contenido inicial'],
            'is_filled' => true,
            'sort_order' => 0,
            'last_edited_by' => $creatorId,
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
            ->andReturn($publicPem);

        $this->postJson(
            "/api/v1/documents/{$documentId}/submit",
            [],
            ['Authorization' => 'Bearer '.$token],
        )->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->assertDatabaseHas('document_versions', [
            'document_id' => $documentId,
            'trigger_event' => 'published',
            'triggered_by' => 'creator-doc-uuid-01',
        ]);
        $this->assertDatabaseHas('entity_versions', [
            'versionable_type' => Document::class,
            'versionable_id' => $documentId,
            'status' => 'published',
            'published_by' => 'creator-doc-uuid-01',
            'is_snapshot_immutable' => 1,
        ]);
    }

    /**
     * Usuario compartido (no titular) no puede enviar a revisión → 403 y publica evento de auditoría.
     */
    public function test_shared_user_submit_returns_403_and_publishes_audit_event(): void
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
        ]);

        DocumentShare::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'document_id' => $documentId,
            'user_id' => $sharedId,
            'permission' => 'read',
            'granted_by' => $ownerId,
        ]);

        $auditPublisher = $this->mock(AuditPublisher::class);
        $auditPublisher->shouldReceive('publish')
            ->once()
            ->withArgs(function (
                string $applicationSlug,
                string $entityType,
                string $entityId,
                string $action,
                string $userId,
                ?string $blockId,
                ?array $previousValue,
                ?array $newValue,
            ) use ($documentId, $sharedId): bool {
                return $applicationSlug === 'maya-dms'
                    && $entityType === 'document'
                    && $entityId === $documentId
                    && $action === 'sod_violation'
                    && $userId === $sharedId
                    && ($newValue['level'] ?? null) === 'WARNING'
                    && array_key_exists('ability', $newValue ?? []);
            });

        [$privatePem, $publicPem] = $this->generateRsaKeyPairForTests();
        $token = $this->buildJwtForSub($privatePem, $publicPem, 'kid-sod-sh', $sharedId);

        config([
            'auth.jwt_issuer' => 'test-issuer',
            'auth.jwt_audience' => 'test-audience',
        ]);

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn($publicPem);

        $this->postJson(
            "/api/v1/documents/{$documentId}/submit",
            [],
            ['Authorization' => 'Bearer '.$token],
        )->assertForbidden();
    }

    /**
     * Escenario 2: creador envía a revisión una plantilla sin revisores → se publica automáticamente.
     *
     * El endpoint `publish` exige ser revisor asignado (SoD). Cuando no hay revisores,
     * la publicación es automática al llamar a `submit-review`.
     */
    public function test_creator_submit_review_auto_publishes_when_no_reviewers(): void
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
            ->andReturn($publicPem);

        $response = $this->postJson(
            "/api/v1/templates/{$templateId}/submit-review",
            ['changelog' => 'Primera publicación con changelog obligatorio.'],
            ['Authorization' => 'Bearer '.$token],
        );

        $response->assertOk()
            ->assertJsonPath('data.status', 'published');
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
        ]);

        [$privatePem, $publicPem] = $this->generateRsaKeyPairForTests();
        $token = $this->buildJwtForSub($privatePem, $publicPem, 'kid-sod-del', $ownerId);

        config([
            'auth.jwt_issuer' => 'test-issuer',
            'auth.jwt_audience' => 'test-audience',
        ]);

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn($publicPem);

        $this->postJson(
            "/api/v1/documents/{$documentId}/submit",
            [],
            ['Authorization' => 'Bearer '.$token],
        )->assertOk()
            ->assertJsonPath('data.status', 'published');
    }

    public function test_submit_document_with_reviewer_enters_in_review_and_creates_pending_review(): void
    {
        $ownerId = 'owner-with-reviewer-01';
        $reviewerId = 'reviewer-with-reviewer-02';
        $templateId = (string) Str::uuid();
        $documentId = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $templateId,
            'name' => 'T con revisor',
            'description' => null,
            'study_id' => null,
            'created_by' => $ownerId,
            'status' => 'draft',
            'review_stages' => 1,
            'review_mode' => 'sequential',
        ]);
        TemplateReviewer::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'template_id' => $templateId,
            'user_id' => $reviewerId,
            'stage' => 1,
        ]);

        Document::query()->forceCreate([
            'id' => $documentId,
            'template_id' => $templateId,
            'title' => 'Doc con revisor',
            'study_id' => null,
            'created_by' => $ownerId,
            'owner_id' => $ownerId,
            'status' => 'draft',
        ]);

        [$privatePem, $publicPem] = $this->generateRsaKeyPairForTests();
        $token = $this->buildJwtForSub($privatePem, $publicPem, 'kid-with-reviewer', $ownerId);

        config([
            'auth.jwt_issuer' => 'test-issuer',
            'auth.jwt_audience' => 'test-audience',
        ]);

        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn($publicPem);

        $this->postJson(
            "/api/v1/documents/{$documentId}/submit",
            [],
            ['Authorization' => 'Bearer '.$token],
        )->assertOk()
            ->assertJsonPath('data.status', 'in_review');

        $this->assertDatabaseHas('document_reviews', [
            'document_id' => $documentId,
            'reviewer_id' => $reviewerId,
            'status' => 'pending',
            'stage' => 1,
        ]);
    }
}
