<?php

namespace Tests\Feature;

use App\Repositories\Contracts\ResolvedPermissionReaderInterface;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\UserPermissionsSeeder;
use Database\Seeders\UsersSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Comportamiento del reader sobre `user_resolved_permissions` (stub en
 * testing, vista FDW federada con maya_authorization en local/prod) con
 * datos de {@see database/data/user_permissions_mock.php}.
 *
 * IDs alineados con {@see database/data/users_mock.php} (UUIDs de
 * Keycloak en entorno local).
 */
class ResolvedPermissionReaderIntegrationTest extends TestCase
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

    public function test_returns_ordered_slugs_for_user_with_assignments(): void
    {
        $reader = app(ResolvedPermissionReaderInterface::class);

        $slugs = $reader->findPermissionSlugsByUserId(self::USER_ESO);

        $this->assertSame(
            ['documents.create', 'documents.read', 'templates.read'],
            $slugs,
        );
    }

    public function test_returns_empty_list_when_user_has_no_rows(): void
    {
        $reader = app(ResolvedPermissionReaderInterface::class);

        $slugs = $reader->findPermissionSlugsByUserId(self::USER_WITHOUT_PERMISSIONS);

        $this->assertSame([], $slugs);
        $this->assertFalse(Cache::has('user_permission_slugs:' . self::USER_WITHOUT_PERMISSIONS));

        $this->assertSame([], $reader->findPermissionSlugsByUserId(self::USER_WITHOUT_PERMISSIONS));
    }

    public function test_auditor_mock_returns_ordered_permission_slugs(): void
    {
        $reader = app(ResolvedPermissionReaderInterface::class);

        $slugs = $reader->findPermissionSlugsByUserId(self::USER_AUDITOR);

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
            $slugs,
        );
    }

    public function test_second_call_uses_cache_until_forget(): void
    {
        $reader = app(ResolvedPermissionReaderInterface::class);

        $this->assertNull(Cache::get('user_permission_slugs:' . self::USER_ESO));

        $first = $reader->findPermissionSlugsByUserId(self::USER_ESO);
        $this->assertNotNull(Cache::get('user_permission_slugs:' . self::USER_ESO));

        $second = $reader->findPermissionSlugsByUserId(self::USER_ESO);
        $this->assertSame($first, $second);

        $reader->forgetCachedSlugsForUser(self::USER_ESO);
        $this->assertNull(Cache::get('user_permission_slugs:' . self::USER_ESO));

        $third = $reader->findPermissionSlugsByUserId(self::USER_ESO);
        $this->assertSame($first, $third);
        $this->assertNotNull(Cache::get('user_permission_slugs:' . self::USER_ESO));
    }
}
