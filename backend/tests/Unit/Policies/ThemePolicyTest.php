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

    /**
     * @param  list<string>  $permissions
     */
    private function makeJwtUser(array $permissions): JwtUser
    {
        return new JwtUser([
            'id' => (string) Str::uuid(),
            'sub' => (string) Str::uuid(),
            'permissions' => $permissions,
        ]);
    }

    private function makeTheme(string $status): Theme
    {
        $theme = new Theme;
        $theme->forceFill([
            'id' => (string) Str::uuid(),
            'name' => 'Test',
            'status' => $status,
            'created_by' => (string) Str::uuid(),
        ]);

        return $theme;
    }
}
