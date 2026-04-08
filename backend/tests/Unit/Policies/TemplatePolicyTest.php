<?php

namespace Tests\Unit\Policies;

use App\Models\JwtUser;
use App\Models\Template;
use App\Policies\TemplatePolicy;
use Tests\TestCase;

class TemplatePolicyTest extends TestCase
{
    private TemplatePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new TemplatePolicy;
    }

    public function test_creator_cannot_review_template(): void
    {
        $creatorId = '11111111-1111-1111-1111-111111111111';
        $user      = $this->makeJwtUser($creatorId);
        $template  = $this->makeTemplate(createdBy: $creatorId);

        $this->assertFalse($this->policy->review($user, $template));
    }

    public function test_non_creator_can_review_template(): void
    {
        $creatorId  = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $reviewerId = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
        $user       = $this->makeJwtUser($reviewerId);
        $template   = $this->makeTemplate(createdBy: $creatorId);

        $this->assertTrue($this->policy->review($user, $template));
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

    private function makeTemplate(string $createdBy): Template
    {
        $t = new Template;
        $t->forceFill([
            'created_by' => $createdBy,
            'status'     => 'draft',
        ]);

        return $t;
    }
}
