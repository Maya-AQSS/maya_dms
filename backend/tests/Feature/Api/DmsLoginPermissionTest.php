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
});

it('denies protected API routes without dms.login', function () {
    $response = $this->getJson('/api/v1/dashboard');

    $response->assertForbidden();
});

it('allows protected API routes when user has dms.login', function () {
    DB::table('user_resolved_permissions')->insert([
        'user_id' => $this->userId,
        'permission_slug' => 'dms.login',
    ]);

    // El antiguo /hierarchy fue sustituido por /me/academic-context: protegido
    // por dms.login sin permisos adicionales, ideal para verificar el gate.
    $response = $this->getJson('/api/v1/me/academic-context');

    $response->assertOk();
});

it('does not require dms.login for health endpoints', function () {
    $response = $this->getJson('/api/v1/health/live');

    $response->assertOk();
});

it('allows GET me without dms.login so the frontend can resolve permissions', function () {
    DB::table('user_resolved_permissions')->insert([
        'user_id' => $this->userId,
        'permission_slug' => 'template.show',
    ]);

    $response = $this->getJson('/api/v1/me');

    $response->assertOk();
    expect($response->json('data.permissions'))->toContain('template.show');
});

it('denies GET me when jwt is missing', function () {
    $this->withMiddleware([JwtMiddleware::class]);

    $this->getJson('/api/v1/me')->assertUnauthorized();
});
