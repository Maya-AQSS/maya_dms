<?php

declare(strict_types=1);

namespace Tests\Feature;

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

/**
 * Covers GET /documents/{document}/export.docx.
 *
 * The .docx import endpoint was intentionally removed (import is client-side via
 * the wizard's DocxBlockSplitter), so only export remains. The security-relevant
 * authorization paths are asserted here; the binary-generation happy path is
 * skipped when phpoffice/phpword is not installed in the test environment.
 */
final class DocumentDocxExportTest extends TestCase
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

    private function createDocument(string $createdBy, string $title = 'Export Doc'): string
    {
        $processId = Process::query()->value('id') ?? (string) Str::uuid();
        if (! Process::query()->where('id', $processId)->exists()) {
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

    public function test_unauthenticated_request_is_rejected(): void
    {
        $docId = $this->createDocument($this->userId);

        $response = $this->get("/api/v1/documents/{$docId}/export.docx");

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    public function test_user_without_access_cannot_export_document(): void
    {
        $docId = $this->createDocument($this->userId);

        $otherUserId = (string) Str::uuid();
        $this->assignUserPermissions($otherUserId, [], false);
        $otherHeaders = $this->buildAuthHeaders($otherUserId);

        $response = $this->get("/api/v1/documents/{$docId}/export.docx", $otherHeaders);

        // Either the `user_access` global scope hides the document (404) or the
        // policy gate denies it (403). Both mean "no access".
        $this->assertContains($response->getStatusCode(), [403, 404]);
    }

    public function test_owner_can_export_document_as_docx(): void
    {
        if (! class_exists(\PhpOffice\PhpWord\PhpWord::class) || ! class_exists(\Maya\Editor\Support\DocxExporter::class)) {
            $this->markTestSkipped('phpoffice/phpword (ceedcv-maya/shared-editor-laravel) not installed in this environment.');
        }

        $docId = $this->createDocument($this->userId);

        $response = $this->get("/api/v1/documents/{$docId}/export.docx", $this->authHeaders);

        $response->assertOk();
        $response->assertHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );
        $this->assertStringContainsString('.docx', (string) $response->headers->get('Content-Disposition'));
    }
}
