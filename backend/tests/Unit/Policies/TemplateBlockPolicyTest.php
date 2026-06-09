<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\TemplateVisibilityLevel;
use App\Models\JwtUser;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Policies\TemplateBlockPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TemplateBlockPolicyTest extends TestCase
{
    use RefreshDatabase;

    private TemplateBlockPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new TemplateBlockPolicy;
    }

    // ─── Helper factories ─────────────────────────────────────────────────────

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

    private function makeTemplateBlock(?string $templateCreatedBy): TemplateBlock
    {
        $block = new TemplateBlock;
        $block->forceFill([
            'id' => (string) Str::uuid(),
            'title' => 'Test Block',
        ]);

        if ($templateCreatedBy !== null) {
            $template = Template::query()->forceCreate([
                'id' => (string) Str::uuid(),
                'process_id' => '00000000-0000-0000-0000-000000000001',
                'name' => 'Block Policy Template',
                'description' => null,
                'visibility_level' => TemplateVisibilityLevel::Personal->value,
                'created_by' => $templateCreatedBy,
                'status' => 'draft',
                'review_stages' => 0,
                'review_mode' => 'sequential',
            ]);

            $block->setRelation('template', $template);
        } else {
            $block->setRelation('template', null);
        }

        return $block;
    }

    // ─── Tests ────────────────────────────────────────────────────────────────

    public function test_delete_returns_false_when_template_not_loaded(): void
    {
        $user = $this->makeJwtUser('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
        $block = $this->makeTemplateBlock(null);

        $this->assertFalse($this->policy->delete($user, $block));
    }

    public function test_creator_can_delete_own_template_block_with_block_delete(): void
    {
        $userId = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
        $user = $this->makeJwtUser($userId, ['block.delete', 'template.update']);
        $block = $this->makeTemplateBlock($userId);

        $this->assertTrue($this->policy->delete($user, $block));
    }

    public function test_creator_cannot_delete_without_block_delete_slug(): void
    {
        $userId = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
        $user = $this->makeJwtUser($userId, ['template.update']);
        $block = $this->makeTemplateBlock($userId);

        $this->assertFalse($this->policy->delete($user, $block));
    }

    public function test_non_creator_cannot_delete_template_block_on_personal_template(): void
    {
        $creatorId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $strangerId = 'cccccccc-cccc-cccc-cccc-cccccccccccc';

        $user = $this->makeJwtUser($strangerId, ['block.delete', 'template.update']);
        $block = $this->makeTemplateBlock($creatorId);

        $this->assertFalse($this->policy->delete($user, $block));
    }
}
