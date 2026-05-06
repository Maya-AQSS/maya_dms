<?php

namespace Tests\Unit\Policies;

use App\Models\Document;
use App\Models\DocumentShare;
use App\Models\JwtUser;
use App\Policies\DocumentPolicy;
use Tests\TestCase;

class DocumentPolicyTest extends TestCase
{
    private DocumentPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new DocumentPolicy;
    }

    /**
     * Sin el permiso documents.review, nadie puede revisar (ni terceros, ni creador, ni titular).
     */
    public function test_review_denied_without_documents_review_permission(): void
    {
        $userId = '11111111-1111-1111-1111-111111111111';
        $user   = $this->makeJwtUser($userId);
        $doc    = $this->makeDocument(createdBy: $userId, ownerId: $userId);

        $this->assertFalse($this->policy->review($user, $doc));
    }

    /**
     * Con el permiso documents.review, el creador/titular también puede revisar (sin SoD).
     */
    public function test_creator_owner_can_review_with_permission(): void
    {
        $userId = '11111111-1111-1111-1111-111111111111';
        $user   = $this->makeJwtUser($userId, ['documents.review']);
        $doc    = $this->makeDocument(createdBy: $userId, ownerId: $userId);

        $this->assertTrue($this->policy->review($user, $doc));
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

    public function test_update_allows_non_author_with_documents_update(): void
    {
        $user = $this->makeJwtUser('eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee', ['documents.update']);
        $doc  = $this->makeDocument(
            createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            ownerId: 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
        );

        $this->assertTrue($this->policy->update($user, $doc));
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

    public function test_delete_requires_documents_delete_permission(): void
    {
        $user = $this->makeJwtUser('99999999-9999-9999-9999-999999999999');
        $doc  = $this->makeDocument(
            createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            ownerId: 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
        );

        $this->assertFalse($this->policy->delete($user, $doc));

        $withPerm = $this->makeJwtUser('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', ['documents.delete']);
        $this->assertTrue($this->policy->delete($withPerm, $doc));
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
        $user = $this->makeJwtUser($userId, ['documents.update']);
        $doc = $this->makeDocument(createdBy: $userId, ownerId: $userId);

        $this->assertFalse($this->policy->clone($user, $doc));
    }

    public function test_clone_denied_when_update_denied(): void
    {
        $ownerId = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
        $strangerId = 'cccccccc-cccc-cccc-cccc-cccccccccccc';
        $user = $this->makeJwtUser($strangerId, ['documents.create']);
        $doc = $this->makeDocument(createdBy: $ownerId, ownerId: $ownerId);

        $this->assertFalse($this->policy->clone($user, $doc));
    }

    public function test_clone_allowed_for_owner_with_documents_create(): void
    {
        $userId = '11111111-1111-1111-1111-111111111111';
        $user = $this->makeJwtUser($userId, ['documents.create']);
        $doc = $this->makeDocument(createdBy: $userId, ownerId: $userId);

        $this->assertTrue($this->policy->clone($user, $doc));
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
        $user = $this->makeJwtUser('eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee', ['documents.update']);
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
        $doc = new Document;
        $doc->forceFill([
            'created_by' => $createdBy,
            'owner_id'   => $ownerId,
            'status'     => $status,
        ]);

        return $doc;
    }
}
