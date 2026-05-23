<?php

declare(strict_types=1);

namespace Tests\Feature\TemplateBlocks;

use App\Enums\BlockKind;
use App\Http\Requests\TemplateBlocks\StoreTemplateBlockRequest;
use App\Models\Template;
use App\Models\TemplateBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Verifica la regla "solo un bloque kind=toc por plantilla" + la
 * validación de kind y el default. Se ejerce el FormRequest de forma
 * directa para no depender del seeding completo de auth/permissions
 * del proyecto (los seeders de autenticación se ejecutan en CI con su
 * infraestructura propia, no en la suite unitaria de bloques).
 */
class TocUniquePerTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function makeTemplate(): string
    {
        $pid = (string) Str::uuid();
        DB::table('processes')->insert([
            'id' => $pid,
            'code' => 'TEST'.substr($pid, 0, 6),
            'name' => 'Proceso de test',
            'alias' => 'test',
            'description' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tid = (string) Str::uuid();
        DB::table('templates')->insert([
            'id' => $tid,
            'process_id' => $pid,
            'head_entity_version_id' => null,
            'theme_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $tid;
    }

    /**
     * Ejecuta la validación del FormRequest para un payload dado.
     * Devuelve los errores resultantes (vacío si pasó).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, list<string>>
     */
    private function validateStoreRequest(string $templateId, array $payload): array
    {
        config(['dms.special_blocks_enabled' => true]);

        $request = StoreTemplateBlockRequest::create(
            "/api/v1/templates/{$templateId}/blocks",
            'POST',
            $payload,
        );
        // Bind la instancia Template directamente para que resolveTemplate()
        // corte el lookup via TemplateServiceInterface (cuyo
        // findOrFailWithoutCatalogScope() exige una head EV publicada,
        // fuera del scope de este test de validación).
        $stubTemplate = new Template;
        $stubTemplate->setRawAttributes(['id' => $templateId], true);
        $stubTemplate->exists = true;

        $request->setRouteResolver(function () use ($templateId, $stubTemplate, $request) {
            $route = new \Illuminate\Routing\Route(
                ['POST'],
                'templates/{template}/blocks',
                fn () => null,
            );
            $route->bind($request);
            $route->setParameter('template', $stubTemplate);

            return $route;
        });

        $validator = Validator::make($request->all(), $request->rules());
        $request->withValidator($validator);
        $validator->passes();

        return $validator->errors()->toArray();
    }

    public function test_kind_toc_passes_validation_when_first_one(): void
    {
        $tid = $this->makeTemplate();

        $errors = $this->validateStoreRequest($tid, [
            'title' => 'Índice',
            'kind' => BlockKind::Toc->value,
            'block_state' => 'locked',
            'sort_order' => 0,
        ]);

        $this->assertArrayNotHasKey('kind', $errors);
    }

    public function test_second_toc_block_is_rejected_with_specific_message(): void
    {
        $tid = $this->makeTemplate();

        // Persistir un primer TOC directamente para representar "ya existe uno".
        TemplateBlock::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'template_id' => $tid,
            'title' => 'Índice existente',
            'default_content' => null,
            'description' => null,
            'block_state' => 'locked',
            'kind' => BlockKind::Toc->value,
            'sort_order' => 0,
        ]);

        $errors = $this->validateStoreRequest($tid, [
            'title' => 'Índice duplicado',
            'kind' => BlockKind::Toc->value,
            'block_state' => 'locked',
            'sort_order' => 1,
        ]);

        $this->assertArrayHasKey('kind', $errors);
        $this->assertStringContainsString('Solo se permite un bloque de índice', $errors['kind'][0]);
    }

    public function test_invalid_kind_value_is_rejected(): void
    {
        $tid = $this->makeTemplate();

        $errors = $this->validateStoreRequest($tid, [
            'title' => 'Bloque inválido',
            'kind' => 'invalid_kind',
            'block_state' => 'editable',
            'sort_order' => 0,
        ]);

        $this->assertArrayHasKey('kind', $errors);
    }

    public function test_kind_omitted_falls_back_to_content_in_db(): void
    {
        $tid = $this->makeTemplate();

        // Sin enviar kind: el modelo aplica el default 'content'.
        $bid = (string) Str::uuid();
        $block = new TemplateBlock;
        $block->id = $bid;
        $block->template_id = $tid;
        $block->title = 'Bloque sin kind';
        $block->default_content = null;
        $block->description = null;
        $block->block_state = 'editable';
        $block->sort_order = 0;
        $block->saveQuietly();

        $this->assertDatabaseHas('template_blocks', [
            'id' => $bid,
            'template_id' => $tid,
            'kind' => BlockKind::Content->value,
        ]);
    }

    public function test_feature_flag_off_rejects_non_content_kinds(): void
    {
        $tid = $this->makeTemplate();

        config(['dms.special_blocks_enabled' => false]);

        $request = StoreTemplateBlockRequest::create(
            "/api/v1/templates/{$tid}/blocks",
            'POST',
            [
                'title' => 'Portada',
                'kind' => BlockKind::Cover->value,
                'block_state' => 'editable',
                'sort_order' => 0,
            ],
        );

        $stubTemplate = new Template;
        $stubTemplate->setRawAttributes(['id' => $tid], true);
        $stubTemplate->exists = true;

        $request->setRouteResolver(function () use ($stubTemplate, $request) {
            $route = new \Illuminate\Routing\Route(
                ['POST'],
                'templates/{template}/blocks',
                fn () => null,
            );
            $route->bind($request);
            $route->setParameter('template', $stubTemplate);

            return $route;
        });

        $validator = Validator::make($request->all(), $request->rules());
        $request->withValidator($validator);
        $validator->passes();

        $errors = $validator->errors()->toArray();
        $this->assertArrayHasKey('kind', $errors);
        $this->assertStringContainsString('no están habilitados', $errors['kind'][0]);
    }
}
