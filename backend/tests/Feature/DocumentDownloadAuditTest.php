<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\DocumentDownloaded;
use App\Models\Document;
use App\Models\Process;
use App\Models\Template;
use App\Services\Contracts\DocumentPdfServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Maya\Auth\Contracts\JwksServiceInterface;
use Tests\Concerns\AssignsTestUserPermissions;
use Tests\Concerns\BuildsTestJwt;
use Tests\TestCase;

/**
 * La descarga del PDF (HEAD vivo) debe registrar un evento de auditoría
 * `DocumentDownloaded` (action='downloaded'), que el wildcard del package
 * shared-messaging publica al exchange `maya.audit`.
 *
 * El PDF se genera bajo demanda (síncrono, efímero) — no hay cola ni estado
 * en caché. Se mockea DocumentPdfServiceInterface para evitar invocar WeasyPrint.
 */
final class DocumentDownloadAuditTest extends TestCase
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

        auth()->forgetUser();
        [$privatePem, $publicPem] = $this->generateRsaKeyPairForTests();
        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn($publicPem);

        $token = $this->buildJwtForSub(
            $privatePem,
            $publicPem,
            'kid-'.substr($this->userId, 0, 8),
            $this->userId,
            'test-issuer',
            'test-audience',
            [],
            [],
        );
        $this->authHeaders = ['Authorization' => 'Bearer '.$token];
    }

    private function createDocument(): string
    {
        $processId = (string) Str::uuid();
        Process::query()->forceCreate([
            'id' => $processId,
            'code' => 'PRC'.substr($processId, 0, 4),
            'name' => 'Proc',
            'alias' => 'proc',
        ]);

        $templateId = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $templateId,
            'process_id' => $processId,
            'name' => 'Tpl',
            'created_by' => $this->userId,
            'status' => 'draft',
            'visibility_level' => 'personal',
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        $docId = (string) Str::uuid();
        Document::query()->forceCreate([
            'id' => $docId,
            'created_by' => $this->userId,
            'owner_id' => $this->userId,
            'template_id' => $templateId,
            'process_id' => $processId,
            'title' => 'Doc auditable',
            'status' => 'published',
        ]);

        return $docId;
    }

    public function test_pdf_download_dispatches_downloaded_audit_event(): void
    {
        Event::fake([DocumentDownloaded::class]);

        $docId = $this->createDocument();

        // Mock DocumentPdfServiceInterface to return fake PDF bytes.
        // This avoids invoking WeasyPrint in tests.
        $this->mock(DocumentPdfServiceInterface::class)
            ->shouldReceive('generateBytes')
            ->once()
            ->andReturn("%PDF-1.4\n%fake\n");

        $response = $this->get("/api/v1/documents/{$docId}/pdf", $this->authHeaders);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        Event::assertDispatched(DocumentDownloaded::class, function (DocumentDownloaded $e) use ($docId): bool {
            $payload = $e->toAuditPayload();

            return $e->documentId === $docId
                && $payload['action'] === 'downloaded'
                && $payload['entityType'] === 'document'
                && $payload['applicationSlug'] === 'maya-dms'
                && $e->userId === $this->userId;
        });
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $docId = $this->createDocument();

        $response = $this->get("/api/v1/documents/{$docId}/pdf");

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    public function test_user_without_access_cannot_download_pdf(): void
    {
        $docId = $this->createDocument();

        $otherUserId = (string) Str::uuid();
        $this->assignUserPermissions($otherUserId, [], false);

        auth()->forgetUser();
        [$privatePem, $publicPem] = $this->generateRsaKeyPairForTests();
        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn($publicPem);
        $token = $this->buildJwtForSub(
            $privatePem, $publicPem,
            'kid-other', $otherUserId,
            'test-issuer', 'test-audience',
            [], [],
        );
        $otherHeaders = ['Authorization' => 'Bearer '.$token];

        $response = $this->get("/api/v1/documents/{$docId}/pdf", $otherHeaders);

        $this->assertContains($response->getStatusCode(), [403, 404]);
    }
}
