<?php

namespace Tests\Unit\Policies;

use App\Enums\TemplateVisibilityLevel;
use App\Models\JwtUser;
use App\Models\Template;
use App\Policies\TemplatePolicy;
use Illuminate\Support\Facades\Config;
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

    public function test_create_defaults_to_personal_and_allows_any_authenticated_user(): void
    {
        $user = $this->makeJwtUser('cccccccc-cccc-cccc-cccc-cccccccccccc');

        $this->assertTrue($this->policy->create($user));
        $this->assertTrue($this->policy->create($user, TemplateVisibilityLevel::Personal->value));
    }

    public function test_create_shared_visibility_denied_without_privileged_role(): void
    {
        $user = $this->makeJwtUser('dddddddd-dddd-dddd-dddd-dddddddddddd');

        $this->assertFalse($this->policy->create($user, TemplateVisibilityLevel::Global->value));
    }

    public function test_create_shared_visibility_allowed_with_configured_role(): void
    {
        Config::set('auth.template_shared_visibility_roles', ['department-head']);
        $user = $this->makeJwtUser('eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee', ['department-head']);

        $this->assertTrue($this->policy->create($user, TemplateVisibilityLevel::Global->value));
    }

    public function test_update_denied_for_non_creator_without_privileged_role(): void
    {
        $user     = $this->makeJwtUser('ffffffff-ffff-ffff-ffff-ffffffffffff');
        $template = $this->makeTemplate(createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');

        $this->assertFalse($this->policy->update($user, $template));
    }

    public function test_update_allowed_for_coordinator_on_foreign_template(): void
    {
        Config::set('auth.template_shared_visibility_roles', ['director']);
        $user     = $this->makeJwtUser('11111111-2222-3333-4444-555555555555', ['director']);
        $template = $this->makeTemplate(createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');

        $this->assertTrue($this->policy->update($user, $template));
    }

    public function test_update_with_target_shared_visibility_denied_without_role(): void
    {
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $user      = $this->makeJwtUser($creatorId);
        $template  = $this->makeTemplate(createdBy: $creatorId);

        $this->assertFalse($this->policy->update($user, $template, TemplateVisibilityLevel::Study->value));
    }

    public function test_delete_follows_same_rules_as_update(): void
    {
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $creator   = $this->makeJwtUser($creatorId);
        $other     = $this->makeJwtUser('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb');
        $template  = $this->makeTemplate(createdBy: $creatorId);

        $this->assertTrue($this->policy->delete($creator, $template));
        $this->assertFalse($this->policy->delete($other, $template));
    }

    private function makeJwtUser(string $id, array $roles = []): JwtUser
    {
        return new JwtUser([
            'id'              => $id,
            'email'           => null,
            'name'            => null,
            'department'      => null,
            'organization_id' => null,
            'roles'           => $roles,
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
