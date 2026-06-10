<?php

declare(strict_types=1);

use App\Models\Document;
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
        ['user_id' => $this->userId, 'permission_slug' => 'document.index'],
    ]);

    DB::table('processes')->insertOrIgnore([
        'id' => '00000000-0000-0000-0000-000000000001',
        'code' => 'DEFAULT_PROCESS',
        'name' => 'Proceso por defecto',
        'alias' => 'default',
    ]);

    // Plantilla ancla para los documentos del test.
    $this->templateId = (string) Str::uuid();
    Template::query()->forceCreate([
        'id' => $this->templateId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'name' => 'Plantilla ancla',
        'description' => null,
        'visibility_level' => 'personal',
        'delivery_deadline' => null,
        'study_type_id' => null,
        'study_id' => null,
        'module_id' => null,
        'team_id' => null,
        'created_by' => $this->userId,
        'status' => 'published',
        'review_stages' => 0,
        'review_mode' => 'sequential',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

/**
 * Crea un documento propio (creador = titular = usuario de test, visible por scope).
 */
function makeOwnDocument(string $title, array $overrides = []): string
{
    $id = (string) Str::uuid();
    Document::query()->forceCreate(array_merge([
        'id' => $id,
        'template_id' => test()->templateId,
        'title' => $title,
        'study_id' => null,
        'created_by' => test()->userId,
        'owner_id' => test()->userId,
        'status' => 'draft',
        'delivery_deadline' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));

    return $id;
}

it('sorts documents by title ascending', function () {
    $c = makeOwnDocument('Charlie');
    $a = makeOwnDocument('Alfa');
    $b = makeOwnDocument('Bravo');

    $ids = $this->getJson('/api/v1/documents?sort_by=title&sort_dir=asc')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$a, $b, $c]);
});

it('sorts documents by delivery_deadline ascending with nulls last', function () {
    $none = makeOwnDocument('Sin plazo', ['delivery_deadline' => null]);
    $late = makeOwnDocument('Tarde', ['delivery_deadline' => now()->addDays(15)]);
    $soon = makeOwnDocument('Pronto', ['delivery_deadline' => now()->addDays(1)]);

    $ids = $this->getJson('/api/v1/documents?sort_by=delivery_deadline&sort_dir=asc')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$soon, $late, $none]);
});

it('sorts documents by status ascending', function () {
    $published = makeOwnDocument('Pub', ['status' => 'published']);
    $draft = makeOwnDocument('Draft', ['status' => 'draft']);

    $ids = $this->getJson('/api/v1/documents?sort_by=status&sort_dir=asc')
        ->assertOk()
        ->json('data.*.id');

    // 'draft' < 'published' alfabéticamente
    expect($ids)->toBe([$draft, $published]);
});

it('falls back to created_at desc for an invalid sort column', function () {
    $old = makeOwnDocument('Old', ['created_at' => now()->subDays(3)]);
    $new = makeOwnDocument('New', ['created_at' => now()]);

    $ids = $this->getJson('/api/v1/documents?sort_by=evil_injection')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$new, $old]);
});

it('filters documents by favorite_ids (document id)', function () {
    $favId = makeOwnDocument('Favorito');
    makeOwnDocument('Otro');

    $ids = $this->getJson('/api/v1/documents?favorite_ids='.$favId)
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$favId]);
});

it('ignores empty favorite_ids for documents (returns all)', function () {
    makeOwnDocument('Uno');
    makeOwnDocument('Dos');

    $count = count($this->getJson('/api/v1/documents?favorite_ids=')->assertOk()->json('data'));

    expect($count)->toBe(2);
});
