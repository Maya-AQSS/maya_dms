<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\JwtUser;
use App\Models\Theme;
use App\Policies\ThemePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ThemePolicyTest extends TestCase
{
    use RefreshDatabase;

    private ThemePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ThemePolicy;
    }

    public function test_view_any_requires_theme_index(): void
    {
        $withIndex = $this->makeJwtUser(['theme.index']);
        $teacher = $this->makeJwtUser(['dms.login', 'template.index']);

        $this->assertTrue($this->policy->viewAny($withIndex));
        $this->assertFalse($this->policy->viewAny($teacher));
    }

    public function test_view_published_for_template_requires_dms_login(): void
    {
        $loggedIn = $this->makeJwtUser(['dms.login']);
        $anonymous = $this->makeJwtUser([]);

        $this->assertTrue($this->policy->viewPublishedForTemplate($loggedIn));
        $this->assertFalse($this->policy->viewPublishedForTemplate($anonymous));
    }

    public function test_view_allows_theme_show_for_any_status(): void
    {
        $user = $this->makeJwtUser(['theme.show']);
        $draft = $this->makeTheme('draft');

        $this->assertTrue($this->policy->view($user, $draft));
    }

    public function test_view_allows_published_theme_for_template_selector_without_theme_show(): void
    {
        $user = $this->makeJwtUser(['dms.login']);
        $published = $this->makeTheme('published');

        $this->assertTrue($this->policy->view($user, $published));
    }

    public function test_view_denies_draft_theme_without_theme_show(): void
    {
        $user = $this->makeJwtUser(['dms.login']);
        $draft = $this->makeTheme('draft');

        $this->assertFalse($this->policy->view($user, $draft));
    }

    public function test_create_requires_theme_create(): void
    {
        $this->assertFalse($this->policy->create($this->makeJwtUser(['dms.login'])));
        $this->assertTrue($this->policy->create($this->makeJwtUser(['theme.create'])));
    }

    public function test_update_allows_creator_without_theme_update(): void
    {
        $creatorId = (string) Str::uuid();
        $creator = $this->makeJwtUser(['theme.create', 'theme.show'], $creatorId);
        $theme = $this->makeTheme('draft', $creatorId);

        $this->assertTrue($this->policy->update($creator, $theme));
    }

    public function test_update_denies_non_creator_without_theme_update(): void
    {
        $creatorId = (string) Str::uuid();
        $stranger = $this->makeJwtUser(['theme.show'], (string) Str::uuid());
        $theme = $this->makeTheme('draft', $creatorId);

        $this->assertFalse($this->policy->update($stranger, $theme));
    }

    public function test_update_allows_non_creator_with_theme_update(): void
    {
        $creatorId = (string) Str::uuid();
        $editor = $this->makeJwtUser(['theme.show', 'theme.update'], (string) Str::uuid());
        $theme = $this->makeTheme('draft', $creatorId);

        $this->assertTrue($this->policy->update($editor, $theme));
    }

    public function test_delete_allows_creator_without_theme_delete(): void
    {
        $creatorId = (string) Str::uuid();
        $creator = $this->makeJwtUser(['theme.show', 'theme.create'], $creatorId);
        $theme = $this->makeTheme('draft', $creatorId);

        $this->assertTrue($this->policy->delete($creator, $theme));
    }

    public function test_delete_allows_admin_with_theme_delete(): void
    {
        $creatorId = (string) Str::uuid();
        $admin = $this->makeJwtUser(['theme.show', 'theme.delete'], (string) Str::uuid());
        $theme = $this->makeTheme('draft', $creatorId);

        $this->assertTrue($this->policy->delete($admin, $theme));
    }

    public function test_delete_denies_non_creator_without_theme_delete(): void
    {
        $creatorId = (string) Str::uuid();
        $other = $this->makeJwtUser(['theme.show', 'theme.update'], (string) Str::uuid());
        $theme = $this->makeTheme('draft', $creatorId);

        $this->assertFalse($this->policy->delete($other, $theme));
    }

    public function test_clone_requires_theme_clone_and_view(): void
    {
        $creatorId = (string) Str::uuid();
        $user = $this->makeJwtUser(['theme.create', 'theme.show'], $creatorId);
        $theme = $this->makeTheme('published', $creatorId);

        $this->assertFalse($this->policy->clone($user, $theme));

        $cloner = $this->makeJwtUser(['theme.clone', 'theme.show'], $creatorId);
        $this->assertTrue($this->policy->clone($cloner, $theme));
    }

    /**
     * @param  list<string>  $permissions
     */
    private function makeJwtUser(array $permissions, ?string $id = null): JwtUser
    {
        $userId = $id ?? (string) Str::uuid();

        return new JwtUser([
            'id' => $userId,
            'sub' => $userId,
            'permissions' => $permissions,
        ]);
    }

    private function makeTheme(string $status, ?string $createdBy = null): Theme
    {
        $theme = new Theme;
        $theme->forceFill([
            'id' => (string) Str::uuid(),
            'name' => 'Test',
            'status' => $status,
            'created_by' => $createdBy ?? (string) Str::uuid(),
        ]);

        return $theme;
    }
}
