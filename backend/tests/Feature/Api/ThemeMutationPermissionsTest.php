<?php

declare(strict_types=1);

use App\Models\Theme;
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

    foreach (['dms.login', 'theme.index', 'theme.show'] as $slug) {
        DB::table('user_resolved_permissions')->insert([
            'user_id' => $this->userId,
            'permission_slug' => $slug,
        ]);
    }
});

function grantThemeMutationPermission(string $slug): void
{
    DB::table('user_resolved_permissions')->insert([
        'user_id' => test()->userId,
        'permission_slug' => $slug,
    ]);
}

function insertThemeOwnedBy(string $ownerId, string $status = 'draft'): string
{
    $theme = new Theme;
    $theme->id = (string) Str::uuid();
    $theme->name = 'Theme test';
    $theme->status = $status;
    $theme->created_by = $ownerId;
    $theme->palette = ['primary' => '#000000', 'secondary' => '#666666', 'text' => '#111111', 'background' => '#ffffff'];
    $theme->typography = ['heading_font' => 'sans', 'body_font' => 'sans', 'base_size_pt' => 11, 'line_height' => 1.5];
    $theme->layout = ['regions' => [], 'page' => ['size' => 'A4']];
    $theme->accessibility = ['language' => 'es', 'title' => null, 'subject' => null, 'author' => 'CEEDCV'];
    $theme->save();

    return $theme->id;
}

it('denies theme create without theme.create', function () {
    $this->postJson('/api/v1/themes', ['name' => 'Nuevo'])->assertForbidden();
});

it('allows theme create with theme.create', function () {
    grantThemeMutationPermission('theme.create');

    $this->postJson('/api/v1/themes', ['name' => 'Nuevo'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Nuevo');
});

it('allows creator to update own theme without theme.update', function () {
    grantThemeMutationPermission('theme.create');
    $id = insertThemeOwnedBy(test()->userId);

    $this->patchJson("/api/v1/themes/{$id}", ['name' => 'Renombrado'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renombrado');
});

it('denies non creator update without theme.update', function () {
    $otherOwner = (string) Str::uuid();
    $id = insertThemeOwnedBy($otherOwner);

    $this->patchJson("/api/v1/themes/{$id}", ['name' => 'Intruso'])->assertForbidden();
});

it('allows non creator update with theme.update', function () {
    grantThemeMutationPermission('theme.update');
    $otherOwner = (string) Str::uuid();
    $id = insertThemeOwnedBy($otherOwner);

    $this->patchJson("/api/v1/themes/{$id}", ['name' => 'Editado por jefe estudios'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Editado por jefe estudios');
});

it('denies theme clone without theme.clone', function () {
    grantThemeMutationPermission('theme.create');
    $id = insertThemeOwnedBy(test()->userId, 'published');

    $this->postJson("/api/v1/themes/{$id}/clone", [])->assertForbidden();
});

it('allows theme clone with theme.clone', function () {
    grantThemeMutationPermission('theme.create');
    grantThemeMutationPermission('theme.clone');
    $id = insertThemeOwnedBy(test()->userId, 'published');

    $this->postJson("/api/v1/themes/{$id}/clone", ['name' => 'Copia'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Copia');
});

it('allows creator to delete own theme without theme.delete', function () {
    grantThemeMutationPermission('theme.create');
    $id = insertThemeOwnedBy(test()->userId);

    $this->deleteJson("/api/v1/themes/{$id}")->assertNoContent();
});

it('denies non creator delete without theme.delete', function () {
    $otherOwner = (string) Str::uuid();
    $id = insertThemeOwnedBy($otherOwner);

    $this->deleteJson("/api/v1/themes/{$id}")->assertForbidden();
});

it('allows non creator delete with theme.delete', function () {
    grantThemeMutationPermission('theme.delete');
    $otherOwner = (string) Str::uuid();
    $id = insertThemeOwnedBy($otherOwner);

    $this->deleteJson("/api/v1/themes/{$id}")->assertNoContent();
});
