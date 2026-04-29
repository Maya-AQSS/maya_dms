<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Document;
use App\Models\Template;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lcobucci\JWT\Signer\Key\InMemory;
use Maya\Auth\Contracts\JwksServiceInterface;
use Tests\Concerns\BuildsTestJwt;
use Tests\TestCase;

class AuditLogApiTest extends TestCase
{
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

        $this->seed(PermissionsSeeder::class);
    }

    /**
     * @param  list<string>  $realmRoles
     * @param  array<string, mixed>  $extraClaims
     *
     * @return array<string, string>
     */
    private function authHeaders(string $sub, array $realmRoles = [], array $extraClaims = []): array
    {
        auth()->forgetUser();

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
            $realmRoles,
            $extraClaims,
        );

        return ['Authorization' => 'Bearer '.$token];
    }

    private function grantAuditReadOnly(string $userId): void
    {
        DB::table('user_permissions')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'permission_code' => 'audit.read',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array{templateId: string, documentId: string, commentId: string}
     */
    private function seedTemplateDocumentComment(string $ownerId): array
    {
        $templateId = (string) Str::uuid();
        $documentId = (string) Str::uuid();
        $commentId = (string) Str::uuid();

        Template::query()->forceCreate([
            'id' => $templateId,
            'name' => 'Plantilla audit',
            'description' => null,
            'visibility_level' => 'personal',
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $ownerId,
            'status' => 'draft',
            'version' => 1,
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        Document::query()->forceCreate([
            'id' => $documentId,
            'template_id' => $templateId,
            'template_version_id' => null,
            'title' => 'Doc audit',
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'created_by' => $ownerId,
            'owner_id' => $ownerId,
            'status' => 'draft',
            'current_version' => 1,
        ]);

        Comment::query()->forceCreate([
            'id' => $commentId,
            'document_id' => $documentId,
            'document_block_id' => null,
            'parent_id' => null,
            'author_id' => $ownerId,
            'body' => 'Nota',
            'type' => 'general',
            'resolved' => false,
        ]);

        return compact('templateId', 'documentId', 'commentId');
    }

    public function test_participant_can_list_document_audit(): void
    {
        $ownerId = (string) Str::uuid();
        $ids = $this->seedTemplateDocumentComment($ownerId);

        $this->getJson("/api/v1/documents/{$ids['documentId']}/audit", $this->authHeaders($ownerId))
            ->assertOk();
    }

    public function test_intruder_without_audit_read_cannot_list_foreign_document_audit(): void
    {
        $ownerId = (string) Str::uuid();
        $intruderId = (string) Str::uuid();

        $ids = $this->seedTemplateDocumentComment($ownerId);

        $this->getJson("/api/v1/documents/{$ids['documentId']}/audit", $this->authHeaders($intruderId))
            ->assertNotFound();
    }

    public function test_user_with_audit_read_can_list_foreign_document_template_and_comment_audit(): void
    {
        $ownerId = (string) Str::uuid();
        $auditorId = (string) Str::uuid();
        $this->grantAuditReadOnly($auditorId);

        $ids = $this->seedTemplateDocumentComment($ownerId);

        $headers = $this->authHeaders($auditorId);

        $this->getJson("/api/v1/documents/{$ids['documentId']}/audit", $headers)->assertOk();
        $this->getJson("/api/v1/templates/{$ids['templateId']}/audit", $headers)->assertOk();
        $this->getJson("/api/v1/comments/{$ids['commentId']}/audit", $headers)->assertOk();
    }

    public function test_creator_can_list_template_audit(): void
    {
        $ownerId = (string) Str::uuid();
        $ids = $this->seedTemplateDocumentComment($ownerId);

        $this->getJson("/api/v1/templates/{$ids['templateId']}/audit", $this->authHeaders($ownerId))
            ->assertOk();
    }

    public function test_comment_author_can_list_comment_audit(): void
    {
        $ownerId = (string) Str::uuid();
        $ids = $this->seedTemplateDocumentComment($ownerId);

        $this->getJson("/api/v1/comments/{$ids['commentId']}/audit", $this->authHeaders($ownerId))
            ->assertOk();
    }

    public function test_audit_endpoints_return_forbidden_when_process_context_does_not_match(): void
    {
        $ownerId = (string) Str::uuid();
        $ids = $this->seedTemplateDocumentComment($ownerId);
        $headers = $this->authHeaders($ownerId);
        $wrongProcessId = '00000000-0000-0000-0000-000000000999';

        $this->getJson("/api/v1/documents/{$ids['documentId']}/audit?process_id={$wrongProcessId}", $headers)
            ->assertForbidden();
        $this->getJson("/api/v1/templates/{$ids['templateId']}/audit?process_id={$wrongProcessId}", $headers)
            ->assertForbidden();
        $this->getJson("/api/v1/comments/{$ids['commentId']}/audit?process_id={$wrongProcessId}", $headers)
            ->assertForbidden();
    }

    public function test_audit_endpoints_allow_matching_process_context(): void
    {
        $ownerId = (string) Str::uuid();
        $ids = $this->seedTemplateDocumentComment($ownerId);
        $headers = $this->authHeaders($ownerId);

        $processId = (string) Document::query()->whereKey($ids['documentId'])->value('process_id');

        $this->getJson("/api/v1/documents/{$ids['documentId']}/audit?process_id={$processId}", $headers)
            ->assertOk();
        $this->getJson("/api/v1/templates/{$ids['templateId']}/audit?process_id={$processId}", $headers)
            ->assertOk();
        $this->getJson("/api/v1/comments/{$ids['commentId']}/audit?process_id={$processId}", $headers)
            ->assertOk();
    }

}
