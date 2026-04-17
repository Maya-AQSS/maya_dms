<?php

namespace Tests\Feature;

use App\Repositories\Contracts\UserPermissionRepositoryInterface;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\UserPermissionsSeeder;
use Database\Seeders\UsersSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Comportamiento del repositorio de asignaciones con datos de {@see database/data/user_permissions_mock.php}.
 */
class UserPermissionRepositoryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->seed(UsersSourceSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(UserPermissionsSeeder::class);
    }

    public function test_returns_ordered_codes_for_user_with_assignments(): void
    {
        $repo = app(UserPermissionRepositoryInterface::class);

        $codes = $repo->findPermissionCodesByUserId('usr_juan_rodriguez');

        $this->assertSame(['documents.read', 'templates.read'], $codes);
    }

    public function test_returns_empty_list_when_user_has_no_rows(): void
    {
        $repo = app(UserPermissionRepositoryInterface::class);

        $codes = $repo->findPermissionCodesByUserId('usr_lucia_moreno');

        $this->assertSame([], $codes);
    }

    public function test_javier_navarro_mock_has_only_audit_read_assignment(): void
    {
        $repo = app(UserPermissionRepositoryInterface::class);

        $codes = $repo->findPermissionCodesByUserId('usr_javier_navarro');

        $this->assertSame(['audit.read'], $codes);
    }

    public function test_second_call_uses_cache_until_forget(): void
    {
        $repo = app(UserPermissionRepositoryInterface::class);

        $this->assertNull(Cache::get('user_permission_codes:usr_juan_rodriguez'));

        $first = $repo->findPermissionCodesByUserId('usr_juan_rodriguez');
        $this->assertNotNull(Cache::get('user_permission_codes:usr_juan_rodriguez'));

        $second = $repo->findPermissionCodesByUserId('usr_juan_rodriguez');
        $this->assertSame($first, $second);

        $repo->forgetCachedCodesForUser('usr_juan_rodriguez');
        $this->assertNull(Cache::get('user_permission_codes:usr_juan_rodriguez'));

        $third = $repo->findPermissionCodesByUserId('usr_juan_rodriguez');
        $this->assertSame($first, $third);
        $this->assertNotNull(Cache::get('user_permission_codes:usr_juan_rodriguez'));
    }
}
