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

    foreach (['dms.login', 'document.index', 'document.show', 'document.create', 'document.update', 'document.version'] as $slug) {
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
function applySnapshotBlock(string $id, array $overrides = []): array
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
 * Plantilla con v1 (a,b editables) y v2 (a editable cambiado, b eliminado, c nuevo locked).
 * Documento anclado a v1 con contenido propio en a y b.
 *
 * @return array{document_id: string, template_id: string, v1_id: string, v2_id: string, block_a: string, block_b: string, block_c: string}
 */
function seedApplyMigrationScenario(string $ownerId, string $status = 'draft'): array
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

    foreach ([$blockA => BlockState::Editable, $blockB => BlockState::Editable, $blockC => BlockState::Locked] as $bid => $state) {
        DB::table('template_blocks')->insert([
            'id' => $bid,
            'template_id' => $templateId,
            'title' => 'Bloque',
            'default_content' => json_encode([]),
            'block_state' => $state->value,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $v1 = test()->seedCanonicalPublicationForTemplate($templateId, 1, $ownerId, [
        applySnapshotBlock($blockA, ['sort_order' => 0, 'title' => 'A v1']),
        applySnapshotBlock($blockB, ['sort_order' => 1, 'title' => 'B v1']),
    ]);

    $v2 = test()->seedCanonicalPublicationForTemplate($templateId, 2, $ownerId, [
        applySnapshotBlock($blockA, [
            'sort_order' => 0,
            'title' => 'A v2',
            'default_content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'nuevo']]]],
        ]),
        applySnapshotBlock($blockC, [
            'sort_order' => 1,
            'title' => 'C v2',
            'block_state' => BlockState::Locked->value,
            'default_content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'C-LOCKED-DEFAULT']]]],
        ]),
    ]);

    $documentId = (string) Str::uuid();
    Document::query()->forceCreate([
        'id' => $documentId,
        'process_id' => '00000000-0000-0000-0000-000000000001',
        'template_id' => $templateId,
        'template_version_id' => $v1['entity_version_id'],
        'title' => 'Programación origen',
        'delivery_deadline' => now()->addWeek()->toDateString(),
        'created_by' => $ownerId,
        'owner_id' => $ownerId,
        'status' => $status,
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

    return [
        'document_id' => $documentId,
        'template_id' => $templateId,
        'v1_id' => $v1['entity_version_id'],
        'v2_id' => $v2['entity_version_id'],
        'block_a' => $blockA,
        'block_b' => $blockB,
        'block_c' => $blockC,
    ];
}

/**
 * @return array<string, mixed>|null
 */
function fetchDocBlock(string $documentId, string $templateBlockId): ?array
{
    $row = DB::table('document_blocks')
        ->where('document_id', $documentId)
        ->where('template_block_id', $templateBlockId)
        ->whereNull('deleted_at')
        ->first();

    return $row !== null ? (array) $row : null;
}

it('upgrades a document in place: re-anchors, applies content, creates new block, deletes removed', function () {
    $s = seedApplyMigrationScenario(test()->userId);
    $migratedA = [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'A migrado']]]];

    $this->postJson("/api/v1/documents/{$s['document_id']}/apply-template-migration", [
        'target_template_version_id' => $s['v2_id'],
        'migrated_blocks' => [$s['block_a'] => $migratedA],
        'removed_block_actions' => [$s['block_b'] => 'delete'],
    ])->assertOk();

    // Re-anclado a v2.
    $this->assertDatabaseHas('documents', [
        'id' => $s['document_id'],
        'template_version_id' => $s['v2_id'],
    ]);

    // A: contenido migrado aplicado.
    $a = fetchDocBlock($s['document_id'], $s['block_a']);
    expect(json_decode((string) $a['content'], true))->toBe($migratedA);

    // C: bloque nuevo creado (locked → contenido por defecto de plantilla).
    $c = fetchDocBlock($s['document_id'], $s['block_c']);
    expect($c)->not->toBeNull();
    expect(json_decode((string) $c['content'], true))
        ->toBe([['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'C-LOCKED-DEFAULT']]]]);

    // B: eliminado (soft-delete) → no figura como activo.
    expect(fetchDocBlock($s['document_id'], $s['block_b']))->toBeNull();
});

it('keeps a removed block when the action is keep and surfaces it as orphaned', function () {
    $s = seedApplyMigrationScenario(test()->userId);

    $res = $this->postJson("/api/v1/documents/{$s['document_id']}/apply-template-migration", [
        'target_template_version_id' => $s['v2_id'],
        'migrated_blocks' => [$s['block_a'] => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'A']]]]],
        'removed_block_actions' => [$s['block_b'] => 'keep'],
    ])->assertOk();

    // B sigue en el documento.
    expect(fetchDocBlock($s['document_id'], $s['block_b']))->not->toBeNull();

    // En la respuesta (blocksForDisplay), B aparece marcado como huérfano.
    $blocks = collect($res->json('data.blocks'))->keyBy('template_block_id');
    expect($blocks[$s['block_b']]['is_orphaned'])->toBeTrue();
});

it('never overwrites a locked block even if migrated content is sent', function () {
    $s = seedApplyMigrationScenario(test()->userId);

    $this->postJson("/api/v1/documents/{$s['document_id']}/apply-template-migration", [
        'target_template_version_id' => $s['v2_id'],
        'migrated_blocks' => [
            $s['block_a'] => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'A']]]],
            $s['block_c'] => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'INTENTO']]]],
        ],
        'removed_block_actions' => [$s['block_b'] => 'delete'],
    ])->assertOk();

    $c = fetchDocBlock($s['document_id'], $s['block_c']);
    expect(json_decode((string) $c['content'], true))
        ->toBe([['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'C-LOCKED-DEFAULT']]]]);
});

it('returns 422 when target version is not newer than the current anchor', function () {
    $s = seedApplyMigrationScenario(test()->userId);

    $this->postJson("/api/v1/documents/{$s['document_id']}/apply-template-migration", [
        'target_template_version_id' => $s['v1_id'],
    ])->assertStatus(422);
});

it('denies applying migration to a foreign document outside scope', function () {
    $s = seedApplyMigrationScenario(test()->otherUserId);

    $this->postJson("/api/v1/documents/{$s['document_id']}/apply-template-migration", [
        'target_template_version_id' => $s['v2_id'],
    ])->assertNotFound();
});
