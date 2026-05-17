<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TemplateVisibilityLevel;
use App\Models\Template;
use App\Repositories\Contracts\TemplateVersionRepositoryInterface;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\UsersSourceSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsTemplatePublicationAnchor;
use Tests\TestCase;

/**
 * Integration tests for TemplateVersionRepository covering the branches not exercised
 * by the broader TemplatesApiTest suite.
 *
 * Targets (18.2% → ≥80%):
 *   - findOrFail: happy path + not-found + wrong type/status
 *   - findOptional: null when missing / null when wrong type/status / happy path
 *   - findLatestPublishedForTemplate: returns latest / null when none
 *   - findByTemplateIdAndVersionNumber: found / not found
 *   - findPublishedMetaById: returns array / null when not published
 *   - findLatestPublishedMetaForTemplate: happy path
 *   - listForTemplateOrdered: returns collection ordered
 *   - nextVersionNumber: increments correctly
 */
class TemplateVersionRepositoryIntegrationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsTemplatePublicationAnchor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UsersSourceSeeder::class);
        $this->seed(PermissionsSeeder::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * @return array{templateId: string, ownerId: string}
     */
    private function seedTemplate(): array
    {
        $ownerId    = (string) Str::uuid();
        $templateId = (string) Str::uuid();

        Template::query()->forceCreate([
            'id'               => $templateId,
            'process_id'       => '00000000-0000-0000-0000-000000000001',
            'name'             => 'Template Version Test',
            'description'      => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'created_by'       => $ownerId,
            'status'           => 'draft',
            'review_stages'    => 0,
            'review_mode'      => 'sequential',
        ]);

        return ['templateId' => $templateId, 'ownerId' => $ownerId];
    }

    private function repo(): TemplateVersionRepositoryInterface
    {
        return app(TemplateVersionRepositoryInterface::class);
    }

    // ─── findOrFail ───────────────────────────────────────────────────────────

    public function test_find_or_fail_returns_entity_version_for_published_template(): void
    {
        $ctx    = $this->seedTemplate();
        $anchor = $this->seedCanonicalPublicationForTemplate(
            $ctx['templateId'],
            1,
            $ctx['ownerId'],
            [],
        );

        $ev = $this->repo()->findOrFail($anchor['entity_version_id']);

        $this->assertSame($anchor['entity_version_id'], (string) $ev->id);
        $this->assertSame('published', $ev->status);
    }

    public function test_find_or_fail_throws_for_unknown_id(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repo()->findOrFail((string) Str::uuid());
    }

    // ─── findOptional ─────────────────────────────────────────────────────────

    public function test_find_optional_returns_null_when_id_not_found(): void
    {
        $result = $this->repo()->findOptional((string) Str::uuid());

        $this->assertNull($result);
    }

    public function test_find_optional_returns_null_when_entity_version_is_not_published(): void
    {
        $ctx    = $this->seedTemplate();

        // Insert a draft (non-published) entity version directly
        $evId = (string) Str::uuid();
        \Illuminate\Support\Facades\DB::table('entity_versions')->insert([
            'id'                  => $evId,
            'versionable_type'    => \App\Models\Template::class,
            'versionable_id'      => $ctx['templateId'],
            'version_number'      => 1,
            'base_version_id'     => null,
            'change_set'          => null,
            'status'              => 'draft',
            'created_by'          => $ctx['ownerId'],
            'published_by'        => null,
            'published_at'        => null,
            'changelog'           => null,
            'snapshot_data'       => null,
            'is_snapshot_immutable' => false,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        $result = $this->repo()->findOptional($evId);

        $this->assertNull($result);
    }

    public function test_find_optional_returns_entity_version_when_published(): void
    {
        $ctx    = $this->seedTemplate();
        $anchor = $this->seedCanonicalPublicationForTemplate(
            $ctx['templateId'],
            1,
            $ctx['ownerId'],
            [],
        );

        $result = $this->repo()->findOptional($anchor['entity_version_id']);

        $this->assertNotNull($result);
        $this->assertSame($anchor['entity_version_id'], (string) $result->id);
    }

    // ─── findLatestPublishedForTemplate ───────────────────────────────────────

    public function test_find_latest_published_for_template_returns_null_when_none(): void
    {
        $ctx    = $this->seedTemplate();

        $result = $this->repo()->findLatestPublishedForTemplate($ctx['templateId']);

        $this->assertNull($result);
    }

    public function test_find_latest_published_for_template_returns_highest_version(): void
    {
        $ctx = $this->seedTemplate();
        $this->seedCanonicalPublicationForTemplate($ctx['templateId'], 1, $ctx['ownerId'], []);
        $anchor2 = $this->seedCanonicalPublicationForTemplate($ctx['templateId'], 2, $ctx['ownerId'], []);

        $result = $this->repo()->findLatestPublishedForTemplate($ctx['templateId']);

        $this->assertNotNull($result);
        $this->assertSame($anchor2['entity_version_id'], (string) $result->id);
    }

    // ─── findByTemplateIdAndVersionNumber ─────────────────────────────────────

    public function test_find_by_template_id_and_version_number_returns_null_when_not_found(): void
    {
        $ctx    = $this->seedTemplate();

        $result = $this->repo()->findByTemplateIdAndVersionNumber($ctx['templateId'], 99);

        $this->assertNull($result);
    }

    public function test_find_by_template_id_and_version_number_returns_correct_version(): void
    {
        $ctx    = $this->seedTemplate();
        $anchor = $this->seedCanonicalPublicationForTemplate(
            $ctx['templateId'],
            3,
            $ctx['ownerId'],
            [],
        );

        $result = $this->repo()->findByTemplateIdAndVersionNumber($ctx['templateId'], 3);

        $this->assertNotNull($result);
        $this->assertSame($anchor['entity_version_id'], (string) $result->id);
    }

    // ─── findPublishedMetaById ────────────────────────────────────────────────

    public function test_find_published_meta_by_id_returns_null_when_not_found(): void
    {
        $result = $this->repo()->findPublishedMetaById((string) Str::uuid());

        $this->assertNull($result);
    }

    public function test_find_published_meta_by_id_returns_array_for_published_version(): void
    {
        $ctx    = $this->seedTemplate();
        $anchor = $this->seedCanonicalPublicationForTemplate(
            $ctx['templateId'],
            1,
            $ctx['ownerId'],
            [],
        );

        $result = $this->repo()->findPublishedMetaById($anchor['entity_version_id']);

        $this->assertNotNull($result);
        $this->assertSame($anchor['entity_version_id'], $result['id']);
        $this->assertSame(1, $result['version_number']);
        $this->assertIsString($result['changelog']);
    }

    // ─── findLatestPublishedMetaForTemplate ───────────────────────────────────

    public function test_find_latest_published_meta_for_template_returns_null_when_none(): void
    {
        $ctx    = $this->seedTemplate();

        $result = $this->repo()->findLatestPublishedMetaForTemplate($ctx['templateId']);

        $this->assertNull($result);
    }

    public function test_find_latest_published_meta_for_template_returns_meta_array(): void
    {
        $ctx    = $this->seedTemplate();
        $anchor = $this->seedCanonicalPublicationForTemplate(
            $ctx['templateId'],
            1,
            $ctx['ownerId'],
            [],
        );

        $result = $this->repo()->findLatestPublishedMetaForTemplate($ctx['templateId']);

        $this->assertNotNull($result);
        $this->assertSame($anchor['entity_version_id'], $result['id']);
        $this->assertSame(1, $result['version_number']);
    }

    // ─── listForTemplateOrdered ───────────────────────────────────────────────

    public function test_list_for_template_ordered_returns_empty_collection_when_none(): void
    {
        $ctx    = $this->seedTemplate();

        $result = $this->repo()->listForTemplateOrdered($ctx['templateId']);

        $this->assertTrue($result->isEmpty());
    }

    public function test_list_for_template_ordered_returns_versions_in_order(): void
    {
        $ctx    = $this->seedTemplate();
        $this->seedCanonicalPublicationForTemplate($ctx['templateId'], 1, $ctx['ownerId'], []);
        $this->seedCanonicalPublicationForTemplate($ctx['templateId'], 2, $ctx['ownerId'], []);

        $result = $this->repo()->listForTemplateOrdered($ctx['templateId']);

        $this->assertCount(2, $result);
        $versions = $result->pluck('version_number')->all();
        $sorted = $versions;
        sort($sorted);
        $this->assertSame($sorted, $versions);
    }

    // ─── nextVersionNumber ────────────────────────────────────────────────────

    public function test_next_version_number_returns_1_when_no_versions_exist(): void
    {
        $ctx    = $this->seedTemplate();

        $next = $this->repo()->nextVersionNumber($ctx['templateId']);

        $this->assertSame(1, $next);
    }

    public function test_next_version_number_returns_increment_of_latest(): void
    {
        $ctx = $this->seedTemplate();
        $this->seedCanonicalPublicationForTemplate($ctx['templateId'], 1, $ctx['ownerId'], []);

        $next = $this->repo()->nextVersionNumber($ctx['templateId']);

        $this->assertSame(2, $next);
    }
}
