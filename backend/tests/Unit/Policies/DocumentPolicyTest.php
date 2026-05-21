<?php

namespace Tests\Unit\Policies;

use App\Enums\TemplateVisibilityLevel;
use App\Models\Document;
use App\Models\DocumentReview;
use App\Models\DocumentShare;
use App\Models\JwtUser;
use App\Models\Template;
use App\Policies\DocumentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_review_denied_without_documents_review_permission(): void
    {
        $userId = '11111111-1111-1111-1111-111111111111';
        $user   = $this->makeJwtUser($userId);
        $doc    = $this->makeDocument(createdBy: $userId, ownerId: $userId);

        $this->assertFalse($this->policy->review($user, $doc));
    }

    public function test_review_denied_with_permission_but_not_assigned(): void
    {
        $userId = '11111111-1111-1111-1111-111111111111';
        $user   = $this->makeJwtUser($userId, ['document.review']);
        $doc    = $this->makeDocument(createdBy: $userId, ownerId: $userId, status: 'in_review');

        $this->assertFalse($this->policy->review($user, $doc));
    }

    public function test_assigned_reviewer_can_review_with_permission(): void
    {
        $reviewerId = 'cccccccc-cccc-cccc-cccc-cccccccccccc';
        $user       = $this->makeJwtUser($reviewerId, ['document.review']);
        $doc        = $this->makeDocument(
            createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            ownerId: 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
            status: 'in_review',
        );

        DocumentReview::query()->forceCreate([
            'id'          => (string) Str::uuid(),
            'document_id' => $doc->id,
            'reviewer_id' => $reviewerId,
            'stage'       => 1,
        ]);

        $this->assertTrue($this->policy->review($user, $doc->fresh()));
    }

    public function test_assigned_reviewer_without_review_slug_cannot_approve_via_policy(): void
    {
        $reviewerId = 'cccccccc-cccc-cccc-cccc-cccccccccccc';
        $user       = $this->makeJwtUser($reviewerId);
        $doc        = $this->makeDocument(
            createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            ownerId: 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
            status: 'in_review',
        );

        DocumentReview::query()->forceCreate([
            'id'          => (string) Str::uuid(),
            'document_id' => $doc->id,
            'reviewer_id' => $reviewerId,
            'stage'       => 1,
        ]);

        $this->assertFalse($this->policy->review($user, $doc->fresh()));
    }

    /**
     * Solo el titular puede enviar a revisión.
     */
    public function test_owner_can_submit(): void
    {
        $userId = '11111111-1111-1111-1111-111111111111';
        $user   = $this->makeJwtUser($userId);
        $doc    = $this->makeDocument(createdBy: $userId, ownerId: $userId);

        $this->assertTrue($this->policy->submit($user, $doc));
    }

    public function test_delegate_owner_can_submit(): void
    {
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $ownerId   = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
        $user      = $this->makeJwtUser($ownerId);
        $doc       = $this->makeDocument(createdBy: $creatorId, ownerId: $ownerId);

        $this->assertTrue($this->policy->submit($user, $doc));
    }

    public function test_creator_cannot_submit_when_no_longer_owner(): void
    {
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $newOwner  = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
        $user      = $this->makeJwtUser($creatorId);
        $doc       = $this->makeDocument(createdBy: $creatorId, ownerId: $newOwner);

        $this->assertFalse($this->policy->submit($user, $doc));
    }

    public function test_third_party_with_scope_access_cannot_submit_if_not_owner(): void
    {
        $creatorId  = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $ownerId    = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
        $reviewerId = 'cccccccc-cccc-cccc-cccc-cccccccccccc';
        $user       = $this->makeJwtUser($reviewerId);
        $doc        = $this->makeDocument(createdBy: $creatorId, ownerId: $ownerId);

        $this->assertFalse($this->policy->submit($user, $doc));
        $this->assertFalse($this->policy->review($user, $doc));
    }

    public function test_update_allows_creator_or_owner_without_global_permission(): void
    {
        $userId = 'dddddddd-dddd-dddd-dddd-dddddddddddd';
        $user   = $this->makeJwtUser($userId);
        $doc    = $this->makeDocument(createdBy: $userId, ownerId: $userId);

        $this->assertTrue($this->policy->update($user, $doc));
    }

    public function test_update_denied_for_non_author_with_document_update_outside_context(): void
    {
        $user = $this->makeJwtUser('eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee', ['document.update', 'document.show']);
        $doc  = $this->makeDocument(
            createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            ownerId: 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
        );

        $this->assertFalse($this->policy->update($user, $doc));
    }

    public function test_update_denied_for_stranger_without_documents_update(): void
    {
        $user = $this->makeJwtUser('ffffffff-ffff-ffff-ffff-ffffffffffff');
        $doc  = $this->makeDocument(
            createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            ownerId: 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
        );

        $this->assertFalse($this->policy->update($user, $doc));
    }

    public function test_update_allows_collaborator_with_edit_share_when_shares_relation_loaded(): void
    {
        $collabId = 'cccccccc-cccc-cccc-cccc-cccccccccccc';
        $user     = $this->makeJwtUser($collabId);
        $doc      = $this->makeDocument(
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
        $user     = $this->makeJwtUser($collabId);
        $doc      = $this->makeDocument(
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
        $owner   = $this->makeJwtUser($ownerId);
        $doc     = $this->makeDocument(
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
        $user    = $this->makeJwtUser($ownerId);
        $doc     = $this->makeDocument(
            createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            ownerId: $ownerId,
        );

        $this->assertTrue($this->policy->delete($user, $doc));
    }

    public function test_delete_allows_creator_without_global_permission(): void
    {
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $user      = $this->makeJwtUser($creatorId);
        $doc       = $this->makeDocument(
            createdBy: $creatorId,
            ownerId: 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
        );

        $this->assertTrue($this->policy->delete($user, $doc));
    }

    public function test_delete_denied_for_stranger_with_document_delete_outside_context(): void
    {
        $user = $this->makeJwtUser('cccccccc-cccc-cccc-cccc-cccccccccccc', ['document.delete', 'document.show']);
        $doc  = $this->makeDocument(
            createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            ownerId: 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
        );

        $this->assertFalse($this->policy->delete($user, $doc));
    }

    public function test_delete_denies_stranger_without_documents_delete_permission(): void
    {
        $user = $this->makeJwtUser('cccccccc-cccc-cccc-cccc-cccccccccccc');
        $doc  = $this->makeDocument(
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
        $ownerId   = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';

        $owner   = $this->makeJwtUser($ownerId);
        $creator = $this->makeJwtUser($creatorId);
        $doc     = $this->makeDocument(createdBy: $creatorId, ownerId: $ownerId);

        $this->assertTrue($this->policy->publish($owner, $doc));
        $this->assertFalse($this->policy->publish($creator, $doc));
    }

    /**
     * Clonar exige documents.create y capacidad de update sobre el documento origen.
     */
    public function test_clone_denied_without_documents_create(): void
    {
        $userId = '11111111-1111-1111-1111-111111111111';
        $user = $this->makeJwtUser($userId, ['document.update']);
        $doc = $this->makeDocument(createdBy: $userId, ownerId: $userId);

        $this->assertFalse($this->policy->clone($user, $doc));
    }

    public function test_clone_denied_when_update_denied(): void
    {
        $ownerId = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
        $strangerId = 'cccccccc-cccc-cccc-cccc-cccccccccccc';
        $user = $this->makeJwtUser($strangerId, ['document.create']);
        $doc = $this->makeDocument(createdBy: $ownerId, ownerId: $ownerId);

        $this->assertFalse($this->policy->clone($user, $doc));
    }

    public function test_clone_allowed_for_owner_with_documents_create(): void
    {
        $userId = '11111111-1111-1111-1111-111111111111';
        $user = $this->makeJwtUser($userId, ['document.create']);
        $doc = $this->makeDocument(createdBy: $userId, ownerId: $userId, status: 'published');

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
        $ownerId   = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';

        $owner   = $this->makeJwtUser($ownerId);
        $creator = $this->makeJwtUser($creatorId);
        $doc     = $this->makeDocument(createdBy: $creatorId, ownerId: $ownerId);

        $this->assertTrue($this->policy->delegate($owner, $doc));
        $this->assertFalse($this->policy->delegate($creator, $doc));
    }

    public function test_start_revision_denied_when_not_published(): void
    {
        $userId = '11111111-1111-1111-1111-111111111111';
        $user   = $this->makeJwtUser($userId);
        $doc    = $this->makeDocument(createdBy: $userId, ownerId: $userId, status: 'draft');

        $this->assertFalse($this->policy->startRevision($user, $doc));
    }

    public function test_start_revision_allows_owner_when_published_and_can_update(): void
    {
        $userId = '11111111-1111-1111-1111-111111111111';
        $user   = $this->makeJwtUser($userId);
        $doc    = $this->makeDocument(createdBy: $userId, ownerId: $userId, status: 'published');

        $this->assertTrue($this->policy->startRevision($user, $doc));
    }

    public function test_start_revision_allows_documents_update_when_published_and_can_update(): void
    {
        $user = $this->makeJwtUser('eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee', ['document.update']);
        $doc  = $this->makeDocument(
            createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            ownerId: 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
            status: 'published',
        );

        $this->assertTrue($this->policy->startRevision($user, $doc));
    }

    public function test_start_revision_denied_when_published_but_update_denied(): void
    {
        $user = $this->makeJwtUser('ffffffff-ffff-ffff-ffff-ffffffffffff');
        $doc  = $this->makeDocument(
            createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            ownerId: 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
            status: 'published',
        );

        $this->assertFalse($this->policy->startRevision($user, $doc));
    }

    /**
     * @param  list<string>  $permissions
     */
    private function makeJwtUser(string $id, array $permissions = []): JwtUser
    {
        return new JwtUser([
            'id'            => $id,
            'email'         => null,
            'name'          => null,
            'department'    => null,
            'permissions'   => $permissions,
            'scope'         => '',
        ]);
    }

    private function makeDocument(string $createdBy, string $ownerId, string $status = 'draft'): Document
    {
        $templateId = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $templateId,
            'process_id' => '00000000-0000-0000-0000-000000000001',
            'name' => 'Plantilla doc policy',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
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
}
