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
 *
 * IDs alineados con {@see database/data/users_mock.php} (UUIDs de Keycloak en entorno local).
 */
class UserPermissionRepositoryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /** Docente ESPA — lectura catálogo + documentos y alta de documentos (mock local) */
    private const USER_ESO = 'cf8bb92a-0417-4a4c-918a-08dd3fd69165';

    /** Sin filas en user_permissions_mock */
    private const USER_WITHOUT_PERMISSIONS = '00000000-0000-0000-0000-000000000099';

    /** Auditoría — conjunto amplio en mock (sin jerarquía académica; ver user_permissions_mock) */
    private const USER_AUDITOR = 'f6bbe247-c60e-44ea-bfac-93e90c5c27bc';

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

        $codes = $repo->findPermissionCodesByUserId(self::USER_ESO);

        $this->assertSame(
            ['documents.create', 'documents.read', 'templates.read'],
            $codes,
        );
    }

    public function test_returns_empty_list_when_user_has_no_rows(): void
    {
        $repo = app(UserPermissionRepositoryInterface::class);

        $codes = $repo->findPermissionCodesByUserId(self::USER_WITHOUT_PERMISSIONS);

        $this->assertSame([], $codes);
        $this->assertFalse(Cache::has('user_permission_codes:' . self::USER_WITHOUT_PERMISSIONS));

        $this->assertSame([], $repo->findPermissionCodesByUserId(self::USER_WITHOUT_PERMISSIONS));
    }

    public function test_auditor_mock_returns_ordered_permission_codes(): void
    {
        $repo = app(UserPermissionRepositoryInterface::class);

        $codes = $repo->findPermissionCodesByUserId(self::USER_AUDITOR);

        $this->assertSame(
            [
                'audit.read',
                'documents.create',
                'documents.delete',
                'documents.read',
                'documents.review',
                'documents.update',
                'templates.create',
                'templates.delete',
                'templates.read',
                'templates.review',
                'templates.update',
                'users.search',
            ],
            $codes,
        );
    }

    public function test_second_call_uses_cache_until_forget(): void
    {
        $repo = app(UserPermissionRepositoryInterface::class);

        $this->assertNull(Cache::get('user_permission_codes:' . self::USER_ESO));

        $first = $repo->findPermissionCodesByUserId(self::USER_ESO);
        $this->assertNotNull(Cache::get('user_permission_codes:' . self::USER_ESO));

        $second = $repo->findPermissionCodesByUserId(self::USER_ESO);
        $this->assertSame($first, $second);

        $repo->forgetCachedCodesForUser(self::USER_ESO);
        $this->assertNull(Cache::get('user_permission_codes:' . self::USER_ESO));

        $third = $repo->findPermissionCodesByUserId(self::USER_ESO);
        $this->assertSame($first, $third);
        $this->assertNotNull(Cache::get('user_permission_codes:' . self::USER_ESO));
    }
}
