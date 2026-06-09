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

    DB::table('user_resolved_permissions')->insert([
        'user_id' => $this->userId,
        'permission_slug' => 'dms.login',
    ]);
});

function grantProcessPermission(string $slug): void
{
    DB::table('user_resolved_permissions')->insert([
        'user_id' => test()->userId,
        'permission_slug' => $slug,
    ]);
}

function insertTestProcess(string $code = 'PX99'): string
{
    $id = (string) Str::uuid();
    DB::table('processes')->insert([
        'id' => $id,
        'code' => $code,
        'name' => 'Proceso test',
        'alias' => 'px99_test',
        'description' => null,
        'process_parent_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

it('denies process index without process.index', function () {
    $this->getJson('/api/v1/processes')->assertForbidden();
});

it('allows process index with process.index', function () {
    grantProcessPermission('process.index');

    $this->getJson('/api/v1/processes')->assertOk();
});

it('denies process show without process.show', function () {
    grantProcessPermission('process.index');
    $id = insertTestProcess('PX01');

    $this->getJson("/api/v1/processes/{$id}")->assertForbidden();
});

it('allows process show with process.show', function () {
    grantProcessPermission('process.index');
    grantProcessPermission('process.show');
    $id = insertTestProcess('PX02');

    $this->getJson("/api/v1/processes/{$id}")
        ->assertOk()
        ->assertJsonPath('data.id', $id);
});

it('denies process create without process.create', function () {
    $this->postJson('/api/v1/processes', [
        'code' => 'PX03',
        'name' => 'Nuevo',
        'alias' => 'px03',
    ])->assertForbidden();
});

it('allows process create with process.create', function () {
    grantProcessPermission('process.create');

    $this->postJson('/api/v1/processes', [
        'code' => 'PX04',
        'name' => 'Nuevo',
        'alias' => 'px04',
    ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'PX04');
});

it('denies process update without process.update', function () {
    grantProcessPermission('process.show');
    $id = insertTestProcess('PX05');

    $this->patchJson("/api/v1/processes/{$id}", [
        'code' => 'PX05',
        'name' => 'Actualizado',
        'alias' => 'px05_upd',
    ])->assertForbidden();
});

it('allows process update with process.update', function () {
    grantProcessPermission('process.update');
    $id = insertTestProcess('PX06');

    $this->patchJson("/api/v1/processes/{$id}", [
        'code' => 'PX06',
        'name' => 'Actualizado',
        'alias' => 'px06_upd',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Actualizado');
});

it('denies process delete without process.delete', function () {
    grantProcessPermission('process.show');
    $id = insertTestProcess('PX07');

    $this->deleteJson("/api/v1/processes/{$id}")->assertForbidden();
});

it('allows process delete with process.delete when no dependents', function () {
    grantProcessPermission('process.delete');
    $id = insertTestProcess('PX08');

    $this->deleteJson("/api/v1/processes/{$id}")->assertNoContent();
});

it('denies deletion-preview without process.delete', function () {
    grantProcessPermission('process.show');
    $id = insertTestProcess('PX09');

    $this->getJson("/api/v1/processes/{$id}/deletion-preview")->assertForbidden();
});

it('allows deletion-preview with process.delete', function () {
    grantProcessPermission('process.delete');
    $id = insertTestProcess('PX10');

    $this->getJson("/api/v1/processes/{$id}/deletion-preview")
        ->assertOk()
        ->assertJsonStructure(['data' => ['templates_count', 'documents_count', 'subprocess_count']]);
});
