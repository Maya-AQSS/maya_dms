<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TemplateVisibilityLevel;
use App\Models\EntityVersion;
use App\Models\Template;
use App\Services\Contracts\EntityVersionLifecycleServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EntityVersionLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_fails_when_snapshot_data_is_empty(): void
    {
        $templateId = $this->createTemplateForVersioning();
        $version = $this->createEntityVersion($templateId, 'draft');

        $service = app(EntityVersionLifecycleServiceInterface::class);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('snapshot de publicación es obligatorio');
        $service->publish($version->id, [], (string) Str::uuid(), 'Publicación de prueba');
    }

    public function test_publish_marks_snapshot_as_immutable_and_sets_publication_metadata(): void
    {
        $templateId = $this->createTemplateForVersioning();
        $version = $this->createEntityVersion($templateId, 'in_review');
        $actorId = (string) Str::uuid();

        $service = app(EntityVersionLifecycleServiceInterface::class);
        $published = $service->publish(
            $version->id,
            ['name' => 'Snapshot final', 'blocks' => [['id' => 'b1']]],
            $actorId,
            'Versión publicada',
        );

        $this->assertSame('published', $published->status);
        $this->assertTrue($published->isSnapshotImmutable);
        $this->assertSame($actorId, $published->publishedBy);
        $this->assertNotNull($published->publishedAt);
        $this->assertSame('Versión publicada', $published->changelog);
        $this->assertSame('Snapshot final', $published->snapshotData['name']);
    }

    public function test_publish_fails_when_status_is_not_publishable(): void
    {
        $templateId = $this->createTemplateForVersioning();
        $version = $this->createEntityVersion($templateId, 'published');

        $service = app(EntityVersionLifecycleServiceInterface::class);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Solo se puede publicar una versión');
        $service->publish($version->id, ['name' => 'Snapshot'], (string) Str::uuid());
    }

    public function test_publish_fails_when_snapshot_is_already_immutable(): void
    {
        $templateId = $this->createTemplateForVersioning();
        $version = EntityVersion::query()->create([
            'versionable_type' => Template::class,
            'versionable_id' => $templateId,
            'version_number' => 1,
            'change_set' => ['name' => 'Draft base'],
            'snapshot_data' => ['name' => 'Snapshot previo'],
            'is_snapshot_immutable' => true,
            'status' => 'in_review',
            'created_by' => (string) Str::uuid(),
            'published_by' => (string) Str::uuid(),
            'published_at' => now(),
        ]);

        $service = app(EntityVersionLifecycleServiceInterface::class);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('snapshot inmutable publicado');
        $service->publish($version->id, ['name' => 'Snapshot nuevo'], (string) Str::uuid(), 'Changelog');
    }

    public function test_publish_trims_empty_changelog_to_null(): void
    {
        $templateId = $this->createTemplateForVersioning();
        $version = $this->createEntityVersion($templateId, 'draft');
        $actorId = (string) Str::uuid();

        $service = app(EntityVersionLifecycleServiceInterface::class);
        $published = $service->publish(
            $version->id,
            ['name' => 'Snapshot final'],
            $actorId,
            '   ',
        );

        $this->assertSame('published', $published->status);
        $this->assertNull($published->changelog);
    }

    public function test_create_published_snapshot_version_creates_immutable_entity_version(): void
    {
        $templateId = $this->createTemplateForVersioning();
        $actorId = (string) Str::uuid();
        $service = app(EntityVersionLifecycleServiceInterface::class);

        $created = $service->createPublishedSnapshotVersion(
            Template::class,
            $templateId,
            1,
            ['template' => ['id' => $templateId], 'blocks' => []],
            $actorId,
            'Publicación inicial',
        );

        $this->assertSame('published', $created->status);
        $this->assertSame(1, $created->versionNumber);
        $this->assertTrue($created->isSnapshotImmutable);
        $this->assertSame($actorId, $created->publishedBy);
        $this->assertSame('Publicación inicial', $created->changelog);
        $this->assertNull($created->baseVersionId);
    }

    public function test_create_published_snapshot_version_links_previous_published_version_as_base(): void
    {
        $templateId = $this->createTemplateForVersioning();
        $service = app(EntityVersionLifecycleServiceInterface::class);
        $actorA = (string) Str::uuid();
        $actorB = (string) Str::uuid();

        $v1 = $service->createPublishedSnapshotVersion(
            Template::class,
            $templateId,
            1,
            ['template' => ['id' => $templateId, 'version' => 1]],
            $actorA,
            'v1',
        );

        $v2 = $service->createPublishedSnapshotVersion(
            Template::class,
            $templateId,
            2,
            ['template' => ['id' => $templateId, 'version' => 2]],
            $actorB,
            'v2',
        );

        $this->assertSame($v1->id, $v2->baseVersionId);
        $this->assertSame(2, $v2->versionNumber);
    }

    private function createEntityVersion(string $templateId, string $status): EntityVersion
    {
        return EntityVersion::query()->create([
            'versionable_type' => Template::class,
            'versionable_id' => $templateId,
            'version_number' => 1,
            'change_set' => ['name' => 'Draft base'],
            'status' => $status,
            'created_by' => (string) Str::uuid(),
        ]);
    }

    private function createTemplateForVersioning(): string
    {
        $processId = (string) Str::uuid();
        $suffix = strtoupper(substr(str_replace('-', '', $processId), 0, 8));
        DB::table('processes')->insert([
            'id' => $processId,
            'code' => 'PROC-VER-'.$suffix,
            'name' => 'Proceso versionado',
            'alias' => 'PV-'.$suffix,
            'description' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $templateId = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $templateId,
            'process_id' => $processId,
            'name' => 'Template Lifecycle',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => (string) Str::uuid(),
            'status' => 'draft',
            'review_stages' => 0,
            'review_mode' => 'parallel',
        ]);

        return $templateId;
    }
}
