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

function grantDmsPermission(string $slug): void
{
    DB::table('user_resolved_permissions')->insert([
        'user_id' => test()->userId,
        'permission_slug' => $slug,
    ]);
}

it('denies dashboard without dms.index', function () {
    $this->getJson('/api/v1/dashboard')->assertForbidden();
});

it('allows dashboard with dms.index', function () {
    grantDmsPermission('dms.index');

    $this->getJson('/api/v1/dashboard')->assertOk();
});
