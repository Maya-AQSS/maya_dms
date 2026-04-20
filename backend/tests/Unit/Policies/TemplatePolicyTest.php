<?php

namespace Tests\Unit\Policies;

use App\Enums\TemplateVisibilityLevel;
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

    public function test_view_any_requires_templates_read(): void
    {
        $sin = $this->makeJwtUser('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
        $con = $this->makeJwtUser('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb', ['templates.read']);

        $this->assertFalse($this->policy->viewAny($sin));
        $this->assertTrue($this->policy->viewAny($con));
    }

    public function test_view_requires_templates_read(): void
    {
        $creatorId = 'cccccccc-cccc-cccc-cccc-cccccccccccc';
        $sin       = $this->makeJwtUser('dddddddd-dddd-dddd-dddd-dddddddddddd');
        $con       = $this->makeJwtUser('eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee', ['templates.read']);
        $template  = $this->makeTemplate($creatorId);

        $this->assertFalse($this->policy->view($sin, $template));
        $this->assertTrue($this->policy->view($con, $template));
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

    public function test_create_shared_visibility_denied_without_templates_create(): void
    {
        $user = $this->makeJwtUser('dddddddd-dddd-dddd-dddd-dddddddddddd');

        $this->assertFalse($this->policy->create($user, TemplateVisibilityLevel::Global->value));
    }

    public function test_create_shared_visibility_allowed_with_templates_create(): void
    {
        $user = $this->makeJwtUser('eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee', ['templates.create']);

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
        $user     = $this->makeJwtUser('11111111-2222-3333-4444-555555555555', ['templates.update']);
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

    public function test_delete_non_creator_requires_templates_delete(): void
    {
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $creator     = $this->makeJwtUser($creatorId);
        $sinPermisos = $this->makeJwtUser('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb');
        $conDelete   = $this->makeJwtUser('cccccccc-cccc-cccc-cccc-cccccccccccc', ['templates.delete']);

        $t1 = $this->makeTemplate(createdBy: $creatorId);
        $t2 = $this->makeTemplate(createdBy: $creatorId);
        $t3 = $this->makeTemplate(createdBy: $creatorId);

        $this->assertTrue($this->policy->delete($creator, $t1));
        $this->assertTrue($this->policy->delete($conDelete, $t2));
        $this->assertFalse($this->policy->delete($sinPermisos, $t3));
    }

    public function test_update_denied_for_user_with_only_templates_delete(): void
    {
        $user     = $this->makeJwtUser('dddddddd-dddd-dddd-dddd-dddddddddddd', ['templates.delete']);
        $template = $this->makeTemplate(createdBy: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');

        $this->assertFalse($this->policy->update($user, $template));
    }

    /**
     * @param  list<string>  $permissions
     */
    private function makeJwtUser(string $id, array $permissions = []): JwtUser
    {
        return new JwtUser([
            'id'           => $id,
            'email'        => null,
            'name'         => null,
            'department'   => null,
            'permissions'  => $permissions,
            'scope'        => '',
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
