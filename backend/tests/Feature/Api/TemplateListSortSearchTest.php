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
        ['user_id' => $this->userId, 'permission_slug' => 'dms.login'],
        ['user_id' => $this->userId, 'permission_slug' => 'template.index'],
    ]);

    DB::table('processes')->insertOrIgnore([
        'id' => '00000000-0000-0000-0000-000000000001',
        'code' => 'DEFAULT_PROCESS',
        'name' => 'Proceso por defecto',
        'alias' => 'default',
    ]);
});

/**
 * Crea una plantilla propia del usuario de test (visible por scope de creador).
 */
function makeOwnTemplate(string $name, array $overrides = []): string
{
    $id = (string) Str::uuid();
    Template::query()->forceCreate(array_merge([
        'id' => $id,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'name' => $name,
        'description' => null,
        'visibility_level' => 'personal',
        'delivery_deadline' => null,
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
    ], $overrides));

    return $id;
}

it('sorts templates by name ascending', function () {
    $gamma = makeOwnTemplate('Gamma');
    $alpha = makeOwnTemplate('Alpha');
    $beta = makeOwnTemplate('Beta');

    $ids = $this->getJson('/api/v1/templates?sort_by=name&sort_dir=asc')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$alpha, $beta, $gamma]);
});

it('sorts templates by name descending', function () {
    $gamma = makeOwnTemplate('Gamma');
    $alpha = makeOwnTemplate('Alpha');
    $beta = makeOwnTemplate('Beta');

    $ids = $this->getJson('/api/v1/templates?sort_by=name&sort_dir=desc')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$gamma, $beta, $alpha]);
});

it('falls back to updated_at desc for an invalid sort column', function () {
    $old = makeOwnTemplate('Old', ['updated_at' => now()->subDays(3)]);
    $new = makeOwnTemplate('New', ['updated_at' => now()]);

    $ids = $this->getJson('/api/v1/templates?sort_by=evil_injection')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$new, $old]);
});

it('sorts by delivery_deadline ascending with nulls last', function () {
    $noDeadline = makeOwnTemplate('Sin plazo', ['delivery_deadline' => null]);
    $late = makeOwnTemplate('Tarde', ['delivery_deadline' => now()->addDays(20)]);
    $soon = makeOwnTemplate('Pronto', ['delivery_deadline' => now()->addDays(2)]);

    $ids = $this->getJson('/api/v1/templates?sort_by=delivery_deadline&sort_dir=asc')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$soon, $late, $noDeadline]);
});

it('searches templates by template name (accent/case insensitive)', function () {
    $match = makeOwnTemplate('Programación Didáctica');
    makeOwnTemplate('Memoria Final');

    $ids = $this->getJson('/api/v1/templates?search=programacion')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$match]);
});

it('filters templates by favorite_ids (head version id)', function () {
    $favId = makeOwnTemplate('Favorita');
    makeOwnTemplate('Otra');
    $headEv = (string) Template::query()->withoutGlobalScopes()->find($favId)->head_entity_version_id;

    $ids = $this->getJson('/api/v1/templates?favorite_ids='.$headEv)
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$favId]);
});

it('ignores empty favorite_ids (returns all)', function () {
    makeOwnTemplate('Una');
    makeOwnTemplate('Dos');

    $count = count($this->getJson('/api/v1/templates?favorite_ids=')->assertOk()->json('data'));

    expect($count)->toBe(2);
});

it('accepts odoo company id as study_type_id filter', function () {
    $match = makeOwnTemplate('Del tipo Odoo', ['study_type_id' => '2']);
    makeOwnTemplate('De otro tipo', ['study_type_id' => '3']);

    $ids = $this->getJson('/api/v1/templates?study_type_id=2')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$match]);
});
