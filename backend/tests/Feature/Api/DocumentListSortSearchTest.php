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

it('filters documents by module_id (snapshot académico)', function () {
    $moduleA = (string) Str::uuid();
    $moduleB = (string) Str::uuid();
    $match = makeOwnDocument('Del módulo A', ['module_id' => $moduleA]);
    makeOwnDocument('Del módulo B', ['module_id' => $moduleB]);

    $ids = $this->getJson('/api/v1/documents?module_id='.$moduleA)
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$match]);
});

it('filters documents by study_id (snapshot académico)', function () {
    $studyA = (string) Str::uuid();
    $studyB = (string) Str::uuid();
    $match = makeOwnDocument('Del estudio A', ['study_id' => $studyA]);
    makeOwnDocument('Del estudio B', ['study_id' => $studyB]);

    $ids = $this->getJson('/api/v1/documents?study_id='.$studyA)
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$match]);
});

it('filters documents by study_type_id (snapshot académico)', function () {
    $typeA = (string) Str::uuid();
    $typeB = (string) Str::uuid();
    $match = makeOwnDocument('Del tipo A', ['study_type_id' => $typeA]);
    makeOwnDocument('Del tipo B', ['study_type_id' => $typeB]);

    $ids = $this->getJson('/api/v1/documents?study_type_id='.$typeA)
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$match]);
});

it('combines academic filters as AND (tipo + estudio + módulo)', function () {
    $type = (string) Str::uuid();
    $study = (string) Str::uuid();
    $module = (string) Str::uuid();
    $match = makeOwnDocument('Coincide todo', [
        'study_type_id' => $type,
        'study_id' => $study,
        'module_id' => $module,
    ]);
    // Mismo tipo y estudio, otro módulo → excluido por el AND de módulo.
    makeOwnDocument('Otro módulo', [
        'study_type_id' => $type,
        'study_id' => $study,
        'module_id' => (string) Str::uuid(),
    ]);

    $ids = $this->getJson('/api/v1/documents?study_type_id='.$type.'&study_id='.$study.'&module_id='.$module)
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$match]);
});

it('accepts odoo company id as study_type_id filter', function () {
    $match = makeOwnDocument('Del tipo Odoo', ['study_type_id' => '2']);
    makeOwnDocument('De otro tipo', ['study_type_id' => '3']);

    $ids = $this->getJson('/api/v1/documents?study_type_id=2')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$match]);
});
