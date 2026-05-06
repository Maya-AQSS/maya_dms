<?php

namespace Tests\Feature;

use App\Enums\TemplateVisibilityLevel;
use App\Models\Document;
use App\Models\EntityVersion;
use App\Models\JwtUser;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class EntityVersionsModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_entity_versions_support_polymorphic_template_and_document_versions(): void
    {
        $actorId = (string) Str::uuid();
        auth()->setUser(new JwtUser([
            'id' => $actorId,
            'email' => null,
            'name' => null,
            'department' => null,
            'permissions' => ['templates.read'],
            'scope' => '',
        ]));

        $processId = (string) Str::uuid();
        DB::table('processes')->insert([
            'id' => $processId,
            'code' => 'P-TEST',
            'name' => 'Proceso test',
            'alias' => 'PR-TEST',
            'description' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $templateId = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $templateId,
            'process_id' => $processId,
            'name' => 'Template v1',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => $actorId,
            'status' => 'published',
            'version' => 1,
            'review_stages' => 0,
            'review_mode' => 'parallel',
        ]);

        $documentId = (string) Str::uuid();
        Document::query()->forceCreate([
            'id' => $documentId,
            'process_id' => $processId,
            'template_id' => $templateId,
            'template_version_id' => null,
            'title' => 'Documento v1',
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'delivery_deadline' => null,
            'created_by' => $actorId,
            'owner_id' => $actorId,
            'status' => 'draft',
            'current_version' => 1,
            'submitted_at' => null,
            'published_at' => null,
        ]);

        $templateVersion = EntityVersion::query()->create([
            'versionable_type' => Template::class,
            'versionable_id' => $templateId,
            'version_number' => 1,
            'change_set' => ['name' => 'Template v1'],
            'status' => 'published',
            'created_by' => (string) Str::uuid(),
            'published_by' => (string) Str::uuid(),
            'published_at' => now(),
            'snapshot_data' => ['name' => 'Template v1'],
            'is_snapshot_immutable' => true,
        ]);

        $documentVersion = EntityVersion::query()->create([
            'versionable_type' => Document::class,
            'versionable_id' => $documentId,
            'version_number' => 1,
            'change_set' => ['title' => 'Documento v1'],
            'status' => 'draft',
            'created_by' => (string) Str::uuid(),
            'snapshot_data' => null,
            'is_snapshot_immutable' => false,
        ]);

        $this->assertInstanceOf(Template::class, $templateVersion->versionable);
        $this->assertInstanceOf(Document::class, $documentVersion->versionable);
        $this->assertCount(1, Template::query()->findOrFail($templateId)->entityVersions);
        $this->assertCount(1, Document::query()->findOrFail($documentId)->entityVersions);
    }

    public function test_entity_versions_enforce_unique_version_number_per_entity(): void
    {
        $processId = (string) Str::uuid();
        DB::table('processes')->insert([
            'id' => $processId,
            'code' => 'P-TEST-2',
            'name' => 'Proceso test 2',
            'alias' => 'PR-TEST-2',
            'description' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $templateId = (string) Str::uuid();
        Template::query()->forceCreate([
            'id' => $templateId,
            'process_id' => $processId,
            'name' => 'Template',
            'description' => null,
            'visibility_level' => TemplateVisibilityLevel::Personal->value,
            'delivery_deadline' => null,
            'study_type_id' => null,
            'study_id' => null,
            'module_id' => null,
            'team_id' => null,
            'created_by' => (string) Str::uuid(),
            'status' => 'draft',
            'version' => 1,
            'review_stages' => 0,
            'review_mode' => 'parallel',
        ]);

        EntityVersion::query()->create([
            'versionable_type' => Template::class,
            'versionable_id' => $templateId,
            'version_number' => 1,
            'change_set' => ['name' => 'v1'],
            'status' => 'draft',
            'created_by' => (string) Str::uuid(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        EntityVersion::query()->create([
            'versionable_type' => Template::class,
            'versionable_id' => $templateId,
            'version_number' => 1,
            'change_set' => ['name' => 'v1-duplicada'],
            'status' => 'draft',
            'created_by' => (string) Str::uuid(),
        ]);
    }
}
