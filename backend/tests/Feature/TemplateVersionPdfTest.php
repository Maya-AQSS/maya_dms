<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\TemplateDownloaded;
use App\Models\Process;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Services\Contracts\TemplatePdfServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Maya\Auth\Contracts\JwksServiceInterface;
use Tests\Concerns\AssignsTestUserPermissions;
use Tests\Concerns\BuildsTestJwt;
use Tests\Concerns\SeedsTemplatePublicationAnchor;
use Tests\TestCase;

/**
 * GET /api/v1/templates/{template}/versions/{version}/pdf
 *
 * - 200 con permiso viewHistory y versión publicada existente.
 * - 404 sin gate viewHistory.
 * - 404 cuando la versión no pertenece a la plantilla.
 * - Dispara evento de auditoría TemplateDownloaded.
 */
final class TemplateVersionPdfTest extends TestCase
{
    use AssignsTestUserPermissions;
    use BuildsTestJwt;
    use RefreshDatabase;
    use SeedsTemplatePublicationAnchor;

    private string $userId;

    /** @var array<string, string> */
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
        $this->assignUserPermissions($this->userId, ['template.history.view']);

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

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function tiptapDoc(string $text): array
    {
        return [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]],
            ],
        ];
    }

    /**
     * Crea proceso + plantilla publicada + bloque + snapshot, devuelve IDs.
     *
     * @return array{templateId: string, entityVersionId: string}
     */
    private function createPublishedTemplate(): array
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
            'name' => 'Tpl versión',
            'created_by' => $this->userId,
            'status' => 'published',
            'visibility_level' => 'personal',
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        $blockId = (string) Str::uuid();
        TemplateBlock::query()->forceCreate([
            'id' => $blockId,
            'template_id' => $templateId,
            'block_type' => 'content',
            'title' => 'Bloque',
            'default_content' => $this->tiptapDoc('Contenido de prueba'),
            'block_state' => 'editable',
            'sort_order' => 1,
        ]);

        $result = $this->seedCanonicalPublicationForTemplate(
            $templateId,
            1,
            $this->userId,
            [
                [
                    'id' => $blockId,
                    'block_type' => 'content',
                    'title' => 'Bloque',
                    'default_content' => $this->tiptapDoc('Contenido de prueba'),
                    'sort_order' => 1,
                ],
            ],
        );

        return [
            'templateId' => $templateId,
            'entityVersionId' => $result['entity_version_id'],
        ];
    }

    // ─── Tests ───────────────────────────────────────────────────────────────

    public function test_returns_pdf_bytes_for_published_version_with_view_history_permission(): void
    {
        $data = $this->createPublishedTemplate();

        // Mock TemplatePdfServiceInterface to avoid invoking WeasyPrint (final class).
        $this->mock(TemplatePdfServiceInterface::class)
            ->shouldReceive('generateForVersion')
            ->once()
            ->andReturn('%PDF-1.4 fake');

        $response = $this->get(
            "/api/v1/templates/{$data['templateId']}/versions/{$data['entityVersionId']}/pdf",
            $this->authHeaders,
        );

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_returns_404_when_user_lacks_view_history_permission(): void
    {
        // Usuario SIN template.history.view.
        $userId2 = (string) Str::uuid();
        $this->assignUserPermissions($userId2, [], false);
        $this->assignUserPermissions($userId2, ['dms.login', 'template.index', 'template.show']);

        [$privatePem2, $publicPem2] = $this->generateRsaKeyPairForTests();
        $this->mock(JwksServiceInterface::class)
            ->shouldReceive('getPublicKey')
            ->andReturn($publicPem2);
        $token2 = $this->buildJwtForSub(
            $privatePem2, $publicPem2, 'kid2', $userId2, 'test-issuer', 'test-audience',
        );

        $data = $this->createPublishedTemplate();

        $response = $this->get(
            "/api/v1/templates/{$data['templateId']}/versions/{$data['entityVersionId']}/pdf",
            ['Authorization' => 'Bearer '.$token2],
        );

        $response->assertNotFound();
    }

    public function test_returns_404_when_version_does_not_belong_to_template(): void
    {
        $data = $this->createPublishedTemplate();

        // Otra plantilla con su propia versión publicada.
        $processId2 = (string) Str::uuid();
        Process::query()->forceCreate([
            'id' => $processId2,
            'code' => 'PRC'.substr($processId2, 0, 4),
            'name' => 'Proc2',
            'alias' => 'proc2',
        ]);
        $templateId2 = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $templateId2,
            'process_id' => $processId2,
            'name' => 'Otra plantilla',
            'created_by' => $this->userId,
            'status' => 'published',
            'visibility_level' => 'personal',
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);
        $otherResult = $this->seedCanonicalPublicationForTemplate(
            $templateId2, 1, $this->userId, [],
        );
        $otherVersionId = $otherResult['entity_version_id'];

        // Petición con el template de la primera plantilla pero la versión de la segunda.
        $response = $this->get(
            "/api/v1/templates/{$data['templateId']}/versions/{$otherVersionId}/pdf",
            $this->authHeaders,
        );

        $response->assertNotFound();
    }

    public function test_pdf_download_dispatches_template_downloaded_audit_event(): void
    {
        Event::fake([TemplateDownloaded::class]);

        $data = $this->createPublishedTemplate();

        $this->mock(TemplatePdfServiceInterface::class)
            ->shouldReceive('generateForVersion')
            ->once()
            ->andReturn('%PDF-1.4 fake');

        $this->get(
            "/api/v1/templates/{$data['templateId']}/versions/{$data['entityVersionId']}/pdf",
            $this->authHeaders,
        )->assertOk();

        Event::assertDispatched(TemplateDownloaded::class, function (TemplateDownloaded $e) use ($data): bool {
            $payload = $e->toAuditPayload();

            return $e->templateId === $data['templateId']
                && $e->userId === $this->userId
                && $payload['action'] === 'downloaded'
                && $payload['entityType'] === 'template'
                && $payload['applicationSlug'] === 'maya-dms'
                && $e->versionId === $data['entityVersionId'];
        });
    }
}
