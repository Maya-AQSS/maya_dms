<?php

declare(strict_types=1);

use App\Enums\BlockState;
use App\Enums\TemplateVisibilityLevel;
use App\Models\Document;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maya\Auth\Middleware\JwtMiddleware;
use Tests\Concerns\SeedsTemplatePublicationAnchor;

uses(RefreshDatabase::class, SeedsTemplatePublicationAnchor::class);

beforeEach(function () {
    config(['cache.default' => 'array']);
    $this->withoutMiddleware([JwtMiddleware::class]);

    $this->userId = (string) Str::uuid();
    $this->otherUserId = (string) Str::uuid();

    $userId = $this->userId;
    $this->app['events']->listen(RouteMatched::class, function ($event) use ($userId) {
        $event->request->attributes->set('jwt_user', ['id' => $userId, 'sub' => $userId]);
    });

    foreach (['dms.login', 'document.index', 'document.show', 'document.create'] as $slug) {
        DB::table('user_resolved_permissions')->insert([
            'user_id' => $this->userId,
            'permission_slug' => $slug,
        ]);
    }
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function migrationSnapshotBlock(string $id, array $overrides = []): array
{
    return array_merge([
        'id' => $id,
        'type' => 'text',
        'title' => 'Bloque',
        'default_content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'def']]]],
        'block_state' => BlockState::Editable->value,
        'mandatory' => false,
        'sort_order' => 0,
    ], $overrides);
}

/**
 * Escenario: plantilla con v1 (bloques a,b) y v2 (a cambiado, b eliminado, c nuevo locked).
 * Documento origen anclado a $anchorVersion con contenido propio en a y b.
 *
 * @return array{document_id: string, block_a: string, block_b: string, block_c: string}
 */
function seedMigrationScenario(string $ownerId, string $anchor = 'v1'): array
{
    $templateId = (string) Str::uuid();
    $blockA = (string) Str::uuid();
    $blockB = (string) Str::uuid();
    $blockC = (string) Str::uuid();

    Template::query()->forceCreate([
        'id' => $templateId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'name' => 'Plantilla',
        'description' => null,
        'visibility_level' => TemplateVisibilityLevel::Global->value,
        'delivery_deadline' => now()->addWeek(),
        'created_by' => $ownerId,
        'status' => 'published',
        'review_stages' => 0,
        'review_mode' => 'parallel',
    ]);

    foreach ([$blockA, $blockB, $blockC] as $i => $bid) {
        DB::table('template_blocks')->insert([
            'id' => $bid,
            'template_id' => $templateId,
            'title' => 'Bloque',
            'default_content' => json_encode([]),
            'block_state' => BlockState::Editable->value,
            'sort_order' => $i,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $v1 = test()->seedCanonicalPublicationForTemplate($templateId, 1, $ownerId, [
        migrationSnapshotBlock($blockA, ['sort_order' => 0, 'title' => 'A v1']),
        migrationSnapshotBlock($blockB, ['sort_order' => 1, 'title' => 'B v1']),
    ]);

    $v2 = test()->seedCanonicalPublicationForTemplate($templateId, 2, $ownerId, [
        migrationSnapshotBlock($blockA, [
            'sort_order' => 0,
            'title' => 'A v2',
            'default_content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'nuevo']]]],
        ]),
        migrationSnapshotBlock($blockC, ['sort_order' => 1, 'title' => 'C v2', 'block_state' => BlockState::Locked->value]),
    ]);

    $anchorVersionId = $anchor === 'v2' ? $v2['entity_version_id'] : $v1['entity_version_id'];

    $documentId = (string) Str::uuid();
    Document::query()->forceCreate([
        'id' => $documentId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'template_id' => $templateId,
        'template_version_id' => $anchorVersionId,
        'title' => 'Programación origen',
        'delivery_deadline' => now()->addWeek()->toDateString(),
        'created_by' => $ownerId,
        'owner_id' => $ownerId,
        'status' => 'draft',
    ]);

    DB::table('document_blocks')->insert([
        [
            'id' => (string) Str::uuid(),
            'document_id' => $documentId,
            'template_block_id' => $blockA,
            'content' => json_encode([['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'mi A']]]]),
            'is_filled' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => (string) Str::uuid(),
            'document_id' => $documentId,
            'template_block_id' => $blockB,
            'content' => json_encode([['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'mi B']]]]),
            'is_filled' => true,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    return ['document_id' => $documentId, 'block_a' => $blockA, 'block_b' => $blockB, 'block_c' => $blockC];
}

it('returns migration payload with classified blocks for the owner', function () {
    $s = seedMigrationScenario(test()->userId);

    $res = $this->getJson("/api/v1/documents/{$s['document_id']}/migration-payload")
        ->assertOk()
        ->assertJsonPath('data.source_version_number', 1)
        ->assertJsonPath('data.target_version_number', 2);

    $blocks = collect($res->json('data.blocks'))->keyBy('template_block_id');

    // A: cambiado, conserva contenido antiguo del documento.
    expect($blocks[$s['block_a']]['new_block'])->toBeFalse();
    expect($blocks[$s['block_a']]['removed_block'])->toBeFalse();
    expect($blocks[$s['block_a']]['old_content'][0]['content'][0]['text'])->toBe('mi A');

    // C: nuevo y bloqueado, sin contenido antiguo.
    expect($blocks[$s['block_c']]['new_block'])->toBeTrue();
    expect($blocks[$s['block_c']]['locked'])->toBeTrue();
    expect($blocks[$s['block_c']]['old_content'])->toBeNull();

    // B: eliminado en v2, conserva contenido antiguo para copia manual.
    expect($blocks[$s['block_b']]['removed_block'])->toBeTrue();
    expect($blocks[$s['block_b']]['old_content'][0]['content'][0]['text'])->toBe('mi B');
});

it('returns 422 when the document is already on the latest template version', function () {
    $s = seedMigrationScenario(test()->userId, anchor: 'v2');

    $this->getJson("/api/v1/documents/{$s['document_id']}/migration-payload")
        ->assertStatus(422);
});

it('denies access to a foreign document outside scope', function () {
    $s = seedMigrationScenario(test()->otherUserId);

    $this->getJson("/api/v1/documents/{$s['document_id']}/migration-payload")
        ->assertNotFound();
});
