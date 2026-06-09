<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Repositories\Contracts\ResolvedPermissionReaderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\AssignsTestUserPermissions;
use Tests\TestCase;

/**
 * Comportamiento del reader sobre `user_resolved_permissions` (stub físico
 * en testing; vista FDW en local/prod apuntando a maya_authorization).
 *
 * Los permisos se insertan directamente con AssignsTestUserPermissions
 * (sin seeders eliminados en ca8b2e6).
 */
class ResolvedPermissionReaderIntegrationTest extends TestCase
{
    use AssignsTestUserPermissions, RefreshDatabase;

    /** Docente ESPA — subconjunto mínimo para validar lectura */
    private const USER_ESO = 'cf8bb92a-0417-4a4c-918a-08dd3fd69165';

    /** Sin filas — debe devolver lista vacía sin cachear */
    private const USER_WITHOUT_PERMISSIONS = '00000000-0000-0000-0000-000000000099';

    /** Auditoría — conjunto amplio para validar orden alfabético */
    private const USER_AUDITOR = 'f6bbe247-c60e-44ea-bfac-93e90c5c27bc';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->assignUserPermissions(self::USER_ESO, [
            'document.create',
            'document.show',
            'template.show',
        ], withAppLogin: false);

        $this->assignUserPermissions(self::USER_AUDITOR, [
            'audit.read',
            'document.create',
            'document.delete',
            'document.show',
            'document.review',
            'document.update',
            'template.create',
            'template.delete',
            'template.show',
            'template.review',
            'template.update',
        ], withAppLogin: false);
    }

    public function test_returns_ordered_slugs_for_user_with_assignments(): void
    {
        $reader = app(ResolvedPermissionReaderInterface::class);

        $slugs = $reader->findPermissionSlugsByUserId(self::USER_ESO);

        $this->assertSame(
            ['document.create', 'document.show', 'template.show'],
            $slugs,
        );
    }

    public function test_returns_empty_list_when_user_has_no_rows(): void
    {
        $reader = app(ResolvedPermissionReaderInterface::class);

        $slugs = $reader->findPermissionSlugsByUserId(self::USER_WITHOUT_PERMISSIONS);

        $this->assertSame([], $slugs);
        $this->assertFalse(Cache::has('user_permission_slugs:'.self::USER_WITHOUT_PERMISSIONS));

        $this->assertSame([], $reader->findPermissionSlugsByUserId(self::USER_WITHOUT_PERMISSIONS));
    }

    public function test_auditor_mock_returns_ordered_permission_slugs(): void
    {
        $reader = app(ResolvedPermissionReaderInterface::class);

        $slugs = $reader->findPermissionSlugsByUserId(self::USER_AUDITOR);

        $this->assertSame(
            [
                'audit.read',
                'document.create',
                'document.delete',
                'document.review',
                'document.show',
                'document.update',
                'template.create',
                'template.delete',
                'template.review',
                'template.show',
                'template.update',
            ],
            $slugs,
        );
    }

    public function test_second_call_uses_cache_until_forget(): void
    {
        $reader = app(ResolvedPermissionReaderInterface::class);

        $this->assertNull(Cache::get('user_permission_slugs:'.self::USER_ESO));

        $first = $reader->findPermissionSlugsByUserId(self::USER_ESO);
        $this->assertNotNull(Cache::get('user_permission_slugs:'.self::USER_ESO));

        $second = $reader->findPermissionSlugsByUserId(self::USER_ESO);
        $this->assertSame($first, $second);

        $reader->forgetCachedSlugsForUser(self::USER_ESO);
        $this->assertNull(Cache::get('user_permission_slugs:'.self::USER_ESO));

        $third = $reader->findPermissionSlugsByUserId(self::USER_ESO);
        $this->assertSame($first, $third);
        $this->assertNotNull(Cache::get('user_permission_slugs:'.self::USER_ESO));
    }
}
