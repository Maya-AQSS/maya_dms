<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TemplateVisibilityLevel;
use App\Models\EntityVersion;
use App\Models\Template;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Services\EntityVersionReconstructionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class EntityVersionReconstructionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconstruct_merges_base_chain_change_sets(): void
    {
        $templateId = $this->createTemplateForVersioning();

        $v1 = EntityVersion::query()->create([
            'versionable_type' => Template::class,
            'versionable_id' => $templateId,
            'version_number' => 1,
            'change_set' => [
                'name' => 'Plantilla base',
                'meta' => ['visibility_level' => 'study_type', 'study_type_id' => 'ST_1'],
                'blocks' => [['id' => 'b1', 'title' => 'Bloque 1']],
            ],
            'status' => 'draft',
            'created_by' => (string) Str::uuid(),
        ]);

        $v2 = EntityVersion::query()->create([
            'versionable_type' => Template::class,
            'versionable_id' => $templateId,
            'version_number' => 2,
            'base_version_id' => $v1->id,
            'change_set' => [
                'name' => 'Plantilla base v2',
                'meta' => ['study_id' => 'S_1'],
                // listas reemplazan completo
                'blocks' => [['id' => 'b1', 'title' => 'Bloque 1 modificado']],
            ],
            'status' => 'draft',
            'created_by' => (string) Str::uuid(),
        ]);

        $service = new EntityVersionReconstructionService(app(EntityVersionRepositoryInterface::class));
        $state = $service->reconstruct($v2->id);

        $this->assertSame('Plantilla base v2', $state['name']);
        $this->assertSame('study_type', $state['meta']['visibility_level']);
        $this->assertSame('ST_1', $state['meta']['study_type_id']);
        $this->assertSame('S_1', $state['meta']['study_id']);
        $this->assertSame('Bloque 1 modificado', $state['blocks'][0]['title']);
    }

    public function test_reconstruct_prefers_snapshot_data_in_target_version(): void
    {
        $templateId = $this->createTemplateForVersioning();

        $base = EntityVersion::query()->create([
            'versionable_type' => Template::class,
            'versionable_id' => $templateId,
            'version_number' => 1,
            'change_set' => ['name' => 'Desde delta'],
            'status' => 'draft',
            'created_by' => (string) Str::uuid(),
        ]);

        $target = EntityVersion::query()->create([
            'versionable_type' => Template::class,
            'versionable_id' => $templateId,
            'version_number' => 2,
            'base_version_id' => $base->id,
            'change_set' => ['name' => 'No debería verse'],
            'snapshot_data' => ['name' => 'Desde snapshot', 'published' => true],
            'is_snapshot_immutable' => true,
            'status' => 'published',
            'created_by' => (string) Str::uuid(),
            'published_by' => (string) Str::uuid(),
            'published_at' => now(),
        ]);

        $service = new EntityVersionReconstructionService(app(EntityVersionRepositoryInterface::class));
        $state = $service->reconstruct($target->id);

        $this->assertSame('Desde snapshot', $state['name']);
        $this->assertTrue($state['published']);
    }

    public function test_reconstruct_fails_when_base_chain_has_cycle(): void
    {
        $templateId = $this->createTemplateForVersioning();

        $v1 = EntityVersion::query()->create([
            'versionable_type' => Template::class,
            'versionable_id' => $templateId,
            'version_number' => 1,
            'change_set' => ['name' => 'v1'],
            'status' => 'draft',
            'created_by' => (string) Str::uuid(),
        ]);

        $v2 = EntityVersion::query()->create([
            'versionable_type' => Template::class,
            'versionable_id' => $templateId,
            'version_number' => 2,
            'base_version_id' => $v1->id,
            'change_set' => ['name' => 'v2'],
            'status' => 'draft',
            'created_by' => (string) Str::uuid(),
        ]);

        // Crea ciclo: v1 -> v2 y v2 -> v1
        $v1->base_version_id = $v2->id;
        $v1->save();

        $service = new EntityVersionReconstructionService(app(EntityVersionRepositoryInterface::class));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ciclo detectado');
        $service->reconstruct($v2->id);
    }

    public function test_reconstruct_handles_base_deletion_via_null_on_delete(): void
    {
        $templateId = $this->createTemplateForVersioning();

        $base = EntityVersion::query()->create([
            'versionable_type' => Template::class,
            'versionable_id' => $templateId,
            'version_number' => 1,
            'change_set' => json_encode(['name' => 'v1']),
            'status' => 'draft',
            'created_by' => (string) Str::uuid(),
        ]);

        $child = EntityVersion::query()->create([
            'versionable_type' => Template::class,
            'versionable_id' => $templateId,
            'version_number' => 2,
            'base_version_id' => $base->id,
            'change_set' => ['name' => 'v2'],
            'status' => 'draft',
            'created_by' => (string) Str::uuid(),
        ]);

        EntityVersion::query()->whereKey($base->id)->delete();
        $child->refresh();
        $this->assertNull($child->base_version_id);

        $service = new EntityVersionReconstructionService(app(EntityVersionRepositoryInterface::class));
        $state = $service->reconstruct($child->id);
        $this->assertSame('v2', $state['name']);
    }

    public function test_reconstruct_fails_when_base_chain_mixes_different_entities(): void
    {
        $templateIdA = $this->createTemplateForVersioning();
        $templateIdB = $this->createTemplateForVersioning();

        $baseFromA = EntityVersion::query()->create([
            'versionable_type' => Template::class,
            'versionable_id' => $templateIdA,
            'version_number' => 1,
            'change_set' => ['name' => 'A v1'],
            'status' => 'draft',
            'created_by' => (string) Str::uuid(),
        ]);

        $targetFromB = EntityVersion::query()->create([
            'versionable_type' => Template::class,
            'versionable_id' => $templateIdB,
            'version_number' => 1,
            'base_version_id' => $baseFromA->id,
            'change_set' => ['name' => 'B v1'],
            'status' => 'draft',
            'created_by' => (string) Str::uuid(),
        ]);

        $service = new EntityVersionReconstructionService(app(EntityVersionRepositoryInterface::class));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('mezcla de entidades detectada');
        $service->reconstruct($targetFromB->id);
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
            'name' => 'Template Versioning',
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
