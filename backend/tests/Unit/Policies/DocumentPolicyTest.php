<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\TemplateVisibilityLevel;
use App\Models\Document;
use App\Models\DocumentReview;
use App\Models\DocumentShare;
use App\Models\JwtUser;
use App\Models\Template;
use App\Policies\DocumentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentPolicyTest extends TestCase
{
    use RefreshDatabase;

    private DocumentPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new DocumentPolicy;
    }

    /**
     * Sin el permiso documents.review, nadie puede revisar (ni terceros, ni creador, ni titular).
     */
    public function test_create_requires_document_create(): void
    {
        $this->assertFalse($this->policy->create($this->makeJwtUser('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa')));
        $this->assertTrue($this->policy->create($this->makeJwtUser('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb', ['document.create'])));
    }

    public function test_view_any_requires_document_index(): void
    {
        $this->assertFalse($this->policy->viewAny($this->makeJwtUser('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa')));
        $this->assertTrue($this->policy->viewAny($this->makeJwtUser('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb', ['document.index'])));
    }

    public function test_view_allows_owner_without_document_show(): void
    {
        $ownerId = '11111111-1111-1111-1111-111111111111';
        $user = $this->makeJwtUser($ownerId);
        $doc = $this->makeDocument(createdBy: $ownerId, ownerId: $ownerId);

        $this->assertTrue($this->policy->view($user, $doc));
    }

    public function test_view_denied_for_stranger_without_document_show(): void
    {
        $user = $this->makeJwtUser('22222222-2222-2222-2222-222222222222');
        $doc = $this->makeDocument(
            createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            ownerId: 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
            status: 'published',
        );

        $this->assertFalse($this->policy->view($user, $doc));
    }

    public function test_view_denied_for_document_delete_outside_academic_context_even_with_show(): void
    {
        $user = $this->makeJwtUser(
            '22222222-2222-2222-2222-222222222222',
            ['document.delete', 'document.show'],
        );
        $doc = $this->makeDocument(
            createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            ownerId: 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
            status: 'published',
        );

        $this->assertFalse($this->policy->view($user, $doc));
    }

    public function test_view_denied_for_foreign_personal_document_with_published_snapshot(): void
    {
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $ownerId = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
        $peerId = '22222222-2222-2222-2222-222222222222';
        auth()->setUser($this->makeJwtUser($peerId, ['document.show']));
        $doc = $this->makeDocument(
            createdBy: $creatorId,
            ownerId: $ownerId,
            status: 'published',
            visibilityLevel: TemplateVisibilityLevel::Personal->value,
        );

        DB::table('entity_versions')->insert([
            'id' => (string) Str::uuid(),
            'versionable_type' => Document::class,
            'versionable_id' => $doc->id,
            'version_number' => 1,
            'base_version_id' => null,
            'change_set' => null,
            'status' => 'published',
            'created_by' => $creatorId,
            'published_by' => $ownerId,
            'published_at' => now(),
            'changelog' => 'v1',
            'snapshot_data' => json_encode(['document' => ['id' => $doc->id]], JSON_THROW_ON_ERROR),
            'is_snapshot_immutable' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $peer = $this->makeJwtUser($peerId, ['document.show']);

        $this->assertFalse($this->policy->view($peer, $doc));
    }

    public function test_review_denied_without_documents_review_permission(): void
    {
        $userId = '11111111-1111-1111-1111-111111111111';
        $user = $this->makeJwtUser($userId);
        $doc = $this->makeDocument(createdBy: $userId, ownerId: $userId);

        $this->assertFalse($this->policy->review($user, $doc));
    }

    public function test_review_denied_with_permission_but_not_assigned(): void
    {
        $userId = '11111111-1111-1111-1111-111111111111';
        $user = $this->makeJwtUser($userId, ['document.review']);
        $doc = $this->makeDocument(createdBy: $userId, ownerId: $userId, status: 'in_review');

        $this->assertFalse($this->policy->review($user, $doc));
    }

    public function test_assigned_reviewer_can_review_with_permission(): void
    {
        $reviewerId = 'cccccccc-cccc-cccc-cccc-cccccccccccc';
        $user = $this->makeJwtUser($reviewerId, ['document.review']);
        $doc = $this->makeDocument(
            createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            ownerId: 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
            status: 'in_review',
        );

        DocumentReview::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'document_id' => $doc->id,
            'reviewer_id' => $reviewerId,
            'stage' => 1,
        ]);

        $this->assertTrue($this->policy->review($user, $doc->fresh()));
    }

    public function test_assigned_reviewer_without_review_slug_cannot_approve_via_policy(): void
    {
        $reviewerId = 'cccccccc-cccc-cccc-cccc-cccccccccccc';
        $user = $this->makeJwtUser($reviewerId);
        $doc = $this->makeDocument(
            createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            ownerId: 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
            status: 'in_review',
        );

        DocumentReview::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'document_id' => $doc->id,
            'reviewer_id' => $reviewerId,
            'stage' => 1,
        ]);

        $this->assertFalse($this->policy->review($user, $doc->fresh()));
    }

    /**
     * Solo el titular puede enviar a revisión.
     */
    public function test_owner_can_submit(): void
    {
        $userId = '11111111-1111-1111-1111-111111111111';
        $user = $this->makeJwtUser($userId);
        $doc = $this->makeDocument(createdBy: $userId, ownerId: $userId);

        $this->assertTrue($this->policy->submit($user, $doc));
    }

    public function test_delegate_owner_can_submit(): void
    {
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $ownerId = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
        $user = $this->makeJwtUser($ownerId);
        $doc = $this->makeDocument(createdBy: $creatorId, ownerId: $ownerId);

        $this->assertTrue($this->policy->submit($user, $doc));
    }

    public function test_creator_cannot_submit_when_no_longer_owner(): void
    {
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $newOwner = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
        $user = $this->makeJwtUser($creatorId);
        $doc = $this->makeDocument(createdBy: $creatorId, ownerId: $newOwner);

        $this->assertFalse($this->policy->submit($user, $doc));
    }

    public function test_third_party_with_scope_access_cannot_submit_if_not_owner(): void
    {
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $ownerId = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
        $reviewerId = 'cccccccc-cccc-cccc-cccc-cccccccccccc';
        $user = $this->makeJwtUser($reviewerId);
        $doc = $this->makeDocument(createdBy: $creatorId, ownerId: $ownerId);

        $this->assertFalse($this->policy->submit($user, $doc));
        $this->assertFalse($this->policy->review($user, $doc));
    }

    public function test_update_allows_creator_or_owner_without_global_permission(): void
    {
        $userId = 'dddddddd-dddd-dddd-dddd-dddddddddddd';
        $user = $this->makeJwtUser($userId);
        $doc = $this->makeDocument(createdBy: $userId, ownerId: $userId);

        $this->assertTrue($this->policy->update($user, $doc));
    }

    public function test_update_denied_for_non_author_with_document_update_outside_context(): void
    {
        $user = $this->makeJwtUser('eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee', ['document.update', 'document.show']);
        $doc = $this->makeDocument(
            createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            ownerId: 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
        );

        $this->assertFalse($this->policy->update($user, $doc));
    }

    public function test_update_denied_for_stranger_without_documents_update(): void
    {
        $user = $this->makeJwtUser('ffffffff-ffff-ffff-ffff-ffffffffffff');
        $doc = $this->makeDocument(
            createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            ownerId: 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
        );

        $this->assertFalse($this->policy->update($user, $doc));
    }

    public function test_update_allows_collaborator_with_edit_share_when_shares_relation_loaded(): void
    {
        $collabId = 'cccccccc-cccc-cccc-cccc-cccccccccccc';
        $user = $this->makeJwtUser($collabId);
        $doc = $this->makeDocument(
            createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            ownerId: 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
        );
        $share = new DocumentShare;
        $share->forceFill(['user_id' => $collabId, 'permission' => 'edit']);
        $doc->setRelation('shares', collect([$share]));

        $this->assertTrue($this->policy->update($user, $doc));
    }

    public function test_update_denies_collaborator_with_read_share_when_shares_relation_loaded(): void
    {
        $collabId = 'cccccccc-cccc-cccc-cccc-cccccccccccc';
        $user = $this->makeJwtUser($collabId);
        $doc = $this->makeDocument(
            createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            ownerId: 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
        );
        $share = new DocumentShare;
        $share->forceFill(['user_id' => $collabId, 'permission' => 'read']);
        $doc->setRelation('shares', collect([$share]));

        $this->assertFalse($this->policy->update($user, $doc));
    }

    public function test_share_allows_only_owner(): void
    {
        $ownerId = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
        $owner = $this->makeJwtUser($ownerId);
        $doc = $this->makeDocument(
            createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            ownerId: $ownerId,
        );

        $this->assertTrue($this->policy->share($owner, $doc));

        $creator = $this->makeJwtUser('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
        $this->assertFalse($this->policy->share($creator, $doc));
    }

    public function test_delete_allows_owner_without_global_permission(): void
    {
        $ownerId = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
        $user = $this->makeJwtUser($ownerId);
        $doc = $this->makeDocument(
            createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            ownerId: $ownerId,
        );

        $this->assertTrue($this->policy->delete($user, $doc));
    }

    public function test_delete_denied_for_former_creator_no_longer_owner(): void
    {
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $user = $this->makeJwtUser($creatorId);
        $doc = $this->makeDocument(
            createdBy: $creatorId,
            ownerId: 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
        );

        $this->assertFalse($this->policy->delete($user, $doc));
    }

    public function test_delete_denied_for_stranger_with_document_delete_outside_context(): void
    {
        $user = $this->makeJwtUser('cccccccc-cccc-cccc-cccc-cccccccccccc', ['document.delete', 'document.show']);
        $doc = $this->makeDocument(
            createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            ownerId: 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
        );

        $this->assertFalse($this->policy->delete($user, $doc));
    }

    public function test_delete_denies_stranger_without_documents_delete_permission(): void
    {
        $user = $this->makeJwtUser('cccccccc-cccc-cccc-cccc-cccccccccccc');
        $doc = $this->makeDocument(
            createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            ownerId: 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
        );

        $this->assertFalse($this->policy->delete($user, $doc));
    }

    /**
     * Solo el titular puede publicar explícitamente un documento.
     */
    public function test_publish_allowed_only_for_owner(): void
    {
        $ownerId = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';

        $owner = $this->makeJwtUser($ownerId);
        $creator = $this->makeJwtUser($creatorId);
        $doc = $this->makeDocument(createdBy: $creatorId, ownerId: $ownerId);

        $this->assertTrue($this->policy->publish($owner, $doc));
        $this->assertFalse($this->policy->publish($creator, $doc));
    }

    public function test_clone_denied_without_document_create(): void
    {
        $userId = '11111111-1111-1111-1111-111111111111';
        $user = $this->makeJwtUser($userId, ['document.update']);
        $doc = $this->makeDocument(createdBy: $userId, ownerId: $userId, status: 'published');

        $this->assertFalse($this->policy->clone($user, $doc));
    }

    public function test_clone_denied_for_non_titular_without_clone_slug(): void
    {
        $ownerId = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
        $strangerId = 'cccccccc-cccc-cccc-cccc-cccccccccccc';
        $user = $this->makeJwtUser($strangerId, ['document.create', 'document.show', 'document.update']);
        auth()->setUser($user);
        $doc = $this->makeDocument(
            createdBy: $ownerId,
            ownerId: $ownerId,
            status: 'published',
            visibilityLevel: TemplateVisibilityLevel::Global->value,
        );

        $this->assertFalse($this->policy->clone($user, $doc));
    }

    public function test_clone_requires_document_clone_and_update_for_non_titular(): void
    {
        $ownerId = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
        $strangerId = 'cccccccc-cccc-cccc-cccc-cccccccccccc';
        $user = $this->makeJwtUser($strangerId, [
            'document.show',
            'document.create',
            'document.clone',
            'document.update',
        ]);
        auth()->setUser($user);
        $doc = $this->makeDocument(
            createdBy: $ownerId,
            ownerId: $ownerId,
            status: 'published',
            visibilityLevel: TemplateVisibilityLevel::Global->value,
        );
        $studyTypeId = (string) Str::uuid();
        $this->seedPublishedDocumentSnapshot($doc, $ownerId, $studyTypeId);
        $this->enrollUserInStudyType($strangerId, $studyTypeId);

        $this->assertTrue($this->policy->clone($user, $doc));

        $cloneOnly = $this->makeJwtUser($strangerId, ['document.show', 'document.create', 'document.clone']);
        auth()->setUser($cloneOnly);
        $this->assertFalse($this->policy->clone($cloneOnly, $doc));
    }

    public function test_clone_allowed_for_titular_with_document_create(): void
    {
        $userId = '11111111-1111-1111-1111-111111111111';
        $user = $this->makeJwtUser($userId, ['document.create']);
        $doc = $this->makeDocument(createdBy: $userId, ownerId: $userId, status: 'published');
        // Titular: ve por propiedad; clone solo exige un snapshot publicado.
        $this->seedPublishedDocumentSnapshot($doc, $userId, (string) Str::uuid());

        $this->assertTrue($this->policy->clone($user, $doc));
    }

    public function test_clone_denied_for_non_published_document(): void
    {
        $userId = '11111111-1111-1111-1111-111111111111';
        $user = $this->makeJwtUser($userId, ['document.create']);
        $doc = $this->makeDocument(createdBy: $userId, ownerId: $userId, status: 'draft');

        $this->assertFalse($this->policy->clone($user, $doc));
    }

    /**
     * Solo el titular puede delegar la titularidad.
     */
    public function test_delegate_allowed_only_for_owner(): void
    {
        $ownerId = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';

        $owner = $this->makeJwtUser($ownerId);
        $creator = $this->makeJwtUser($creatorId);
        $doc = $this->makeDocument(createdBy: $creatorId, ownerId: $ownerId);

        $this->assertTrue($this->policy->delegate($owner, $doc));
        $this->assertFalse($this->policy->delegate($creator, $doc));
    }

    public function test_start_revision_denied_when_not_published(): void
    {
        $userId = '11111111-1111-1111-1111-111111111111';
        $user = $this->makeJwtUser($userId);
        $doc = $this->makeDocument(createdBy: $userId, ownerId: $userId, status: 'draft');

        $this->assertFalse($this->policy->startRevision($user, $doc));
    }

    public function test_start_revision_allows_owner_when_published_and_can_update(): void
    {
        $userId = '11111111-1111-1111-1111-111111111111';
        $user = $this->makeJwtUser($userId);
        $doc = $this->makeDocument(createdBy: $userId, ownerId: $userId, status: 'published');

        $this->assertTrue($this->policy->startRevision($user, $doc));
    }

    public function test_start_revision_allows_document_version_for_non_titular(): void
    {
        $user = $this->makeJwtUser('eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee', ['document.show', 'document.version']);
        auth()->setUser($user);
        $doc = $this->makeDocument(
            createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            ownerId: 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
            status: 'published',
            visibilityLevel: TemplateVisibilityLevel::Global->value,
        );
        $studyTypeId = (string) Str::uuid();
        $this->seedPublishedDocumentSnapshot($doc, 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', $studyTypeId);
        $this->enrollUserInStudyType('eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee', $studyTypeId);

        $this->assertTrue($this->policy->startRevision($user, $doc));
    }

    public function test_start_revision_denied_for_non_titular_with_only_document_update(): void
    {
        $user = $this->makeJwtUser('eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee', ['document.show', 'document.update']);
        auth()->setUser($user);
        $doc = $this->makeDocument(
            createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            ownerId: 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
            status: 'published',
            visibilityLevel: TemplateVisibilityLevel::Global->value,
        );

        $this->assertFalse($this->policy->startRevision($user, $doc));
    }

    public function test_start_revision_denied_when_published_but_cannot_view(): void
    {
        $user = $this->makeJwtUser('ffffffff-ffff-ffff-ffff-ffffffffffff');
        $doc = $this->makeDocument(
            createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            ownerId: 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
            status: 'published',
        );

        $this->assertFalse($this->policy->startRevision($user, $doc));
    }

    public function test_view_history_allows_titular_without_slug(): void
    {
        $userId = '11111111-1111-1111-1111-111111111111';
        $user = $this->makeJwtUser($userId);
        $doc = $this->makeDocument(createdBy: $userId, ownerId: $userId, status: 'published');

        $this->assertTrue($this->policy->viewHistory($user, $doc));
    }

    public function test_view_history_requires_slug_for_non_titular(): void
    {
        $ownerId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $viewer = $this->makeJwtUser(
            '11111111-2222-3333-4444-555555555555',
            ['document.show', 'document.history.view'],
        );
        auth()->setUser($viewer);
        $doc = $this->makeDocument(
            createdBy: $ownerId,
            ownerId: $ownerId,
            status: 'published',
            visibilityLevel: TemplateVisibilityLevel::Global->value,
        );
        $studyTypeId = (string) Str::uuid();
        $this->seedPublishedDocumentSnapshot($doc, $ownerId, $studyTypeId);
        $this->enrollUserInStudyType('11111111-2222-3333-4444-555555555555', $studyTypeId);
        $this->enrollUserInStudyType('22222222-3333-4444-5555-666666666666', $studyTypeId);

        $this->assertTrue($this->policy->viewHistory($viewer, $doc));

        $noSlug = $this->makeJwtUser('22222222-3333-4444-5555-666666666666', ['document.show']);
        auth()->setUser($noSlug);
        $this->assertFalse($this->policy->viewHistory($noSlug, $doc));
    }

    /**
     * @param  list<string>  $permissions
     */
    private function makeJwtUser(string $id, array $permissions = []): JwtUser
    {
        return new JwtUser([
            'id' => $id,
            'email' => null,
            'name' => null,
            'department' => null,
            'permissions' => $permissions,
            'scope' => '',
        ]);
    }

    private function makeDocument(
        string $createdBy,
        string $ownerId,
        string $status = 'draft',
        string $visibilityLevel = TemplateVisibilityLevel::Personal->value,
    ): Document {
        $templateId = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $templateId,
            'process_id' => '00000000-0000-0000-0000-000000000001',
            'name' => 'Plantilla doc policy',
            'description' => null,
            'visibility_level' => $visibilityLevel,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $createdBy,
            'status' => 'draft',
            'review_stages' => 0,
            'review_mode' => 'sequential',
        ]);

        $document = Document::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'process_id' => '00000000-0000-0000-0000-000000000001',
            'template_id' => $templateId,
            'title' => 'Documento unit policy',
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'delivery_deadline' => null,
            'created_by' => $createdBy,
            'owner_id' => $ownerId,
            'status' => $status,
        ]);
        $document->refresh();

        return $document;
    }

    /**
     * Crea un snapshot publicado (version_number > 0) para el documento. Necesario
     * para `clone` (exige snapshot publicado) y para la visibilidad de catálogo del
     * scope `user_access` sobre documentos ajenos.
     */
    private function seedPublishedDocumentSnapshot(Document $document, string $publishedBy, string $studyTypeId): void
    {
        DB::table('entity_versions')->insert([
            'id' => (string) Str::uuid(),
            'versionable_type' => Document::class,
            'versionable_id' => $document->id,
            'version_number' => 1,
            'status' => 'published',
            'is_snapshot_immutable' => true,
            'created_by' => $publishedBy,
            'published_by' => $publishedBy,
            'published_at' => now(),
            'changelog' => 'v1',
            'snapshot_data' => json_encode([
                'document' => ['id' => $document->id, 'study_type_id' => $studyTypeId],
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Matricula al usuario en un tipo de estudio para que el solapamiento académico
     * del scope haga visible el documento publicado ajeno.
     */
    private function enrollUserInStudyType(string $userId, string $studyTypeId): void
    {
        DB::table('user_study_types')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'study_type_id' => $studyTypeId,
        ]);
    }
}
