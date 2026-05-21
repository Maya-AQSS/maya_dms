<?php

declare(strict_types=1);

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

    foreach (['dms.login', 'template.index', 'template.show'] as $slug) {
        DB::table('user_resolved_permissions')->insert([
            'user_id' => $this->userId,
            'permission_slug' => $slug,
        ]);
    }
});

function grantTemplateMutationPermission(string $slug): void
{
    DB::table('user_resolved_permissions')->insert([
        'user_id' => test()->userId,
        'permission_slug' => $slug,
    ]);
}

it('allows personal template create without template.create', function () {
    $response = $this->postJson('/api/v1/templates', [
        'name' => 'Plantilla personal',
        'visibility_level' => 'personal',
        'delivery_deadline' => now()->addWeek()->toDateString(),
        'process_id' => '00000000-0000-0000-0000-000000000001',
    ]);

    $response->assertCreated();
});

it('denies shared template create without template.create', function () {
    $response = $this->postJson('/api/v1/templates', [
        'name' => 'Plantilla global',
        'visibility_level' => 'global',
        'delivery_deadline' => now()->addWeek()->toDateString(),
        'process_id' => '00000000-0000-0000-0000-000000000001',
    ]);

    $response->assertForbidden();
});

it('allows shared template create with template.create', function () {
    grantTemplateMutationPermission('template.create');

    $response = $this->postJson('/api/v1/templates', [
        'name' => 'Plantilla global',
        'visibility_level' => 'global',
        'delivery_deadline' => now()->addWeek()->toDateString(),
        'process_id' => '00000000-0000-0000-0000-000000000001',
    ]);

    $response->assertCreated();
});

it('allows creator to delete own template without template.delete', function () {
    $templateId = (string) Str::uuid();

    DB::table('templates')->insert([
        'id' => $templateId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'name' => 'Borrar como creador',
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

    $this->deleteJson("/api/v1/templates/{$templateId}")->assertNoContent();
});

it('denies delete by non creator without template.delete', function () {
    grantTemplateMutationPermission('template.create');

    $templateId = (string) Str::uuid();
    $otherCreator = (string) Str::uuid();

    DB::table('templates')->insert([
        'id' => $templateId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'name' => 'Ajena',
        'description' => null,
        'visibility_level' => 'global',
        'delivery_deadline' => now()->addWeek(),
        'study_type_id' => null,
        'study_id' => null,
        'module_id' => null,
        'team_id' => null,
        'created_by' => $otherCreator,
        'status' => 'draft',
        'review_stages' => 0,
        'review_mode' => 'sequential',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->deleteJson("/api/v1/templates/{$templateId}")->assertForbidden();
});
