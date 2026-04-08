<?php

namespace Tests\Unit\Policies;

use App\Models\Document;
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
     * SoD (F-01.3): creador no puede enviar a revisión ni actuar como revisor del mismo documento.
     */
    public function test_creator_cannot_submit_or_review(): void
    {
        $userId = '11111111-1111-1111-1111-111111111111';
        $user   = $this->makeJwtUser($userId);
        $doc    = $this->makeDocument(createdBy: $userId, ownerId: $userId);

        $this->assertFalse($this->policy->submit($user, $doc));
        $this->assertFalse($this->policy->review($user, $doc));
    }

    /**
     * Titular delegado sigue sin poder revisar/aprobar (misma segregación que el creador).
     */
    public function test_delegate_owner_cannot_review_when_not_creator(): void
    {
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $ownerId   = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
        $user      = $this->makeJwtUser($ownerId);
        $doc       = $this->makeDocument(createdBy: $creatorId, ownerId: $ownerId);

        $this->assertFalse($this->policy->review($user, $doc));
        $this->assertFalse($this->policy->submit($user, $doc));
    }

    /**
     * Usuario que no es creador ni titular puede revisar y enviar (autorización de negocio aparte).
     */
    public function test_unrelated_user_can_submit_and_review_from_sod_perspective(): void
    {
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $ownerId   = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
        $reviewerId = 'cccccccc-cccc-cccc-cccc-cccccccccccc';
        $user      = $this->makeJwtUser($reviewerId);
        $doc       = $this->makeDocument(createdBy: $creatorId, ownerId: $ownerId);

        $this->assertTrue($this->policy->submit($user, $doc));
        $this->assertTrue($this->policy->review($user, $doc));
    }

    /**
     * Tras delegar el titular, el creador sigue considerado "conflictivo" para revisión/aprobación y envío.
     */
    public function test_creator_still_cannot_submit_or_review_after_delegating_ownership(): void
    {
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $newOwner  = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
        $user      = $this->makeJwtUser($creatorId);
        $doc       = $this->makeDocument(createdBy: $creatorId, ownerId: $newOwner);

        $this->assertFalse($this->policy->review($user, $doc));
        $this->assertFalse($this->policy->submit($user, $doc));
    }

    private function makeJwtUser(string $id): JwtUser
    {
        return new JwtUser([
            'id'              => $id,
            'email'           => null,
            'name'            => null,
            'department'      => null,
            'organization_id' => null,
            'roles'           => [],
            'scope'           => '',
        ]);
    }

    private function makeDocument(string $createdBy, string $ownerId): Document
    {
        $doc = new Document;
        $doc->forceFill([
            'created_by' => $createdBy,
            'owner_id'   => $ownerId,
            'status'     => 'draft',
        ]);

        return $doc;
    }
}
