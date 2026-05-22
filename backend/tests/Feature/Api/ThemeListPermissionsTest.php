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

    DB::table('user_resolved_permissions')->insert([
        'user_id' => $this->userId,
        'permission_slug' => 'dms.login',
    ]);
});

function grantThemePermission(string $slug): void
{
    DB::table('user_resolved_permissions')->insert([
        'user_id' => test()->userId,
        'permission_slug' => $slug,
    ]);
}

function insertPublishedTheme(string $name = 'Publicado'): string
{
    $theme = new Theme;
    $theme->id = (string) Str::uuid();
    $theme->name = $name;
    $theme->status = 'published';
    $theme->created_by = (string) Str::uuid();
    $theme->palette = ['primary' => '#000000', 'secondary' => '#666666', 'text' => '#111111', 'background' => '#ffffff'];
    $theme->typography = ['heading_font' => 'sans', 'body_font' => 'sans', 'base_size_pt' => 11, 'line_height' => 1.5];
    $theme->layout = ['regions' => [], 'page' => ['size' => 'A4']];
    $theme->assets = ['logo_path' => null, 'background_image_path' => null, 'watermark_path' => null];
    $theme->accessibility = ['language' => 'es', 'title' => null, 'subject' => null, 'author' => 'CEEDCV'];
    $theme->save();

    return $theme->id;
}

function insertDraftTheme(): string
{
    $theme = new Theme;
    $theme->id = (string) Str::uuid();
    $theme->name = 'Borrador';
    $theme->status = 'draft';
    $theme->created_by = (string) Str::uuid();
    $theme->palette = ['primary' => '#000000', 'secondary' => '#666666', 'text' => '#111111', 'background' => '#ffffff'];
    $theme->typography = ['heading_font' => 'sans', 'body_font' => 'sans', 'base_size_pt' => 11, 'line_height' => 1.5];
    $theme->layout = ['regions' => [], 'page' => ['size' => 'A4']];
    $theme->assets = ['logo_path' => null, 'background_image_path' => null, 'watermark_path' => null];
    $theme->accessibility = ['language' => 'es', 'title' => null, 'subject' => null, 'author' => 'CEEDCV'];
    $theme->save();

    return $theme->id;
}

it('denies theme index without theme.index', function () {
    $this->getJson('/api/v1/themes')->assertForbidden();
});

it('allows theme index with theme.index', function () {
    grantThemePermission('theme.index');

    $this->getJson('/api/v1/themes')->assertOk();
});

it('allows published theme index for template selector with only dms.login', function () {
    insertPublishedTheme();

    $this->getJson('/api/v1/themes?status=published')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('denies published theme index without dms.login', function () {
    DB::table('user_resolved_permissions')->where('user_id', test()->userId)->delete();
    insertPublishedTheme();

    $this->getJson('/api/v1/themes?status=published')->assertForbidden();
});

it('denies theme show without theme.show for draft theme', function () {
    $id = insertDraftTheme();

    $this->getJson("/api/v1/themes/{$id}")->assertForbidden();
});

it('allows theme show with theme.show for draft theme', function () {
    grantThemePermission('theme.show');
    $id = insertDraftTheme();

    $this->getJson("/api/v1/themes/{$id}")
        ->assertOk()
        ->assertJsonPath('data.id', $id);
});

it('allows theme show for published theme with only dms.login', function () {
    $id = insertPublishedTheme();

    $this->getJson("/api/v1/themes/{$id}")
        ->assertOk()
        ->assertJsonPath('data.id', $id);
});

it('denies theme show for published theme without dms.login', function () {
    DB::table('user_resolved_permissions')->where('user_id', test()->userId)->delete();
    $id = insertPublishedTheme();

    $this->getJson("/api/v1/themes/{$id}")->assertForbidden();
});
