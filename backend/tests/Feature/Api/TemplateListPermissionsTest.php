<?php

declare(strict_types=1);

use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maya\Auth\Middleware\JwtMiddleware;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['cache.default' => 'array']);
    $this->withoutMiddleware([JwtMiddleware::class]);

    $this->userId = (string) Str::uuid();

    $userId = $this->userId;
    $this->app['events']->listen(RouteMatched::class, function ($event) use ($userId) {
        $event->request->attributes->set('jwt_user', ['id' => $userId, 'sub' => $userId]);
    });

    DB::table('user_resolved_permissions')->insert([
        'user_id' => $this->userId,
        'permission_slug' => 'dms.login',
    ]);
});

function grantTemplatePermission(string $slug): void
{
    DB::table('user_resolved_permissions')->insert([
        'user_id' => test()->userId,
        'permission_slug' => $slug,
    ]);
}

it('denies template index without template.index', function () {
    grantTemplatePermission('template.show');

    $this->getJson('/api/v1/templates')->assertForbidden();
});

it('allows template index with template.index', function () {
    grantTemplatePermission('template.index');

    $this->getJson('/api/v1/templates')->assertOk();
});

it('denies template show without template.show for non creator', function () {
    grantTemplatePermission('template.index');

    $creatorId = (string) Str::uuid();
    $templateId = (string) Str::uuid();

    DB::table('processes')->insertOrIgnore([
        'id' => '00000000-0000-0000-0000-000000000001',
        'code' => 'DEFAULT_PROCESS',
        'name' => 'Proceso por defecto',
        'alias' => 'default',
    ]);

    Template::query()->forceCreate([
        'id' => $templateId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'name' => 'Plantilla ajena',
        'description' => null,
        'visibility_level' => 'personal',
        'delivery_deadline' => now()->addWeek(),
        'study_type_id' => null,
        'study_id' => null,
        'module_id' => null,
        'team_id' => null,
        'created_by' => $creatorId,
        'status' => 'published',
        'review_stages' => 0,
        'review_mode' => 'sequential',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->getJson("/api/v1/templates/{$templateId}")->assertNotFound();
});

it('allows template show for creator without template.show', function () {
    grantTemplatePermission('template.index');

    $templateId = (string) Str::uuid();

    Template::query()->forceCreate([
        'id' => $templateId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'name' => 'Mi plantilla',
        'description' => null,
        'visibility_level' => 'personal',
        'delivery_deadline' => now()->addWeek(),
        'study_type_id' => null,
        'study_id' => null,
        'module_id' => null,
        'team_id' => null,
        'created_by' => test()->userId,
        'status' => 'draft',
        'review_stages' => 0,
        'review_mode' => 'sequential',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->getJson("/api/v1/templates/{$templateId}")->assertOk();
});

it('allows template show with template.show', function () {
    grantTemplatePermission('template.index');
    grantTemplatePermission('template.show');

    $this->getJson('/api/v1/templates')->assertOk();
});
