<?php

namespace Tests\Unit\Services;

use App\DTOs\Documents\CreateDocumentDto;
use App\Enums\TemplateVisibilityLevel;
use App\Services\TemplateContextResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TemplateContextResolverTest extends TestCase
{
    use RefreshDatabase;

    private TemplateContextResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new TemplateContextResolver();
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function dto(array $overrides = []): CreateDocumentDto
    {
        return new CreateDocumentDto(
            templateId:        $overrides['templateId']  ?? 'tpl-1',
            title:             $overrides['title']        ?? 'Doc',
            createdBy:         $overrides['createdBy']    ?? 'user-1',
            ownerId:           $overrides['ownerId']      ?? 'user-1',
            processId:         $overrides['processId']    ?? 'proc-1',
            studyTypeId:       $overrides['studyTypeId']  ?? null,
            studyId:           $overrides['studyId']      ?? null,
            moduleId:          $overrides['moduleId']     ?? null,
            teamId:            $overrides['teamId']       ?? null,
        );
    }

    private function meta(array $overrides = []): array
    {
        return array_merge([
            'visibility_level' => TemplateVisibilityLevel::Global->value,
            'study_type_id'    => null,
            'study_id'         => null,
            'module_id'        => null,
            'team_id'          => null,
        ], $overrides);
    }

    private function seedStudyType(string $id = 'st-1', string $name = 'Grado'): void
    {
        DB::table('study_types')->insertOrIgnore(['id' => $id, 'name' => $name]);
    }

    private function seedStudy(string $id = 's-1', string $studyTypeId = 'st-1', string $name = 'Estudio'): void
    {
        $this->seedStudyType($studyTypeId);
        DB::table('studies')->insertOrIgnore(['id' => $id, 'study_type_id' => $studyTypeId, 'name' => $name]);
    }

    private function seedModule(string $id = 'm-1', string $studyId = 's-1', string $name = 'Módulo'): void
    {
        $this->seedStudy($studyId);
        DB::table('course_modules')->insertOrIgnore(['id' => $id, 'study_id' => $studyId, 'name' => $name]);
    }

    // ── null template meta ─────────────────────────────────────────────────

    public function test_null_meta_passes_through_dto_values(): void
    {
        $dto = $this->dto(['studyTypeId' => 'st-1', 'studyId' => 's-1', 'moduleId' => 'm-1', 'teamId' => 't-1']);

        $result = $this->resolver->resolve($dto, null);

        $this->assertSame(['studyTypeId' => 'st-1', 'studyId' => 's-1', 'moduleId' => 'm-1', 'teamId' => 't-1'], $result);
    }

    // ── Team ───────────────────────────────────────────────────────────────

    public function test_team_visibility_returns_template_team_id(): void
    {
        $result = $this->resolver->resolve(
            $this->dto(['studyTypeId' => 'st-x', 'studyId' => 's-x']),
            $this->meta(['visibility_level' => TemplateVisibilityLevel::Team->value, 'team_id' => 'team-42']),
        );

        $this->assertSame(['studyTypeId' => null, 'studyId' => null, 'moduleId' => null, 'teamId' => 'team-42'], $result);
    }

    public function test_team_visibility_throws_if_template_has_no_team(): void
    {
        $this->expectException(ValidationException::class);

        $this->resolver->resolve(
            $this->dto(),
            $this->meta(['visibility_level' => TemplateVisibilityLevel::Team->value, 'team_id' => null]),
        );
    }

    // ── Personal ───────────────────────────────────────────────────────────

    public function test_personal_visibility_returns_template_context(): void
    {
        $result = $this->resolver->resolve(
            $this->dto(),
            $this->meta([
                'visibility_level' => TemplateVisibilityLevel::Personal->value,
                'study_type_id'    => 'st-1',
                'study_id'         => 's-1',
                'module_id'        => 'm-1',
            ]),
        );

        $this->assertSame(['studyTypeId' => 'st-1', 'studyId' => 's-1', 'moduleId' => 'm-1', 'teamId' => null], $result);
    }

    public function test_personal_visibility_throws_on_mismatched_study_type(): void
    {
        $this->expectException(ValidationException::class);

        $this->resolver->resolve(
            $this->dto(['studyTypeId' => 'st-different']),
            $this->meta(['visibility_level' => TemplateVisibilityLevel::Personal->value, 'study_type_id' => 'st-1']),
        );
    }

    public function test_personal_visibility_throws_if_dto_has_team(): void
    {
        $this->expectException(ValidationException::class);

        $this->resolver->resolve(
            $this->dto(['teamId' => 'team-x']),
            $this->meta(['visibility_level' => TemplateVisibilityLevel::Personal->value]),
        );
    }

    // ── Module ─────────────────────────────────────────────────────────────

    public function test_module_visibility_returns_template_module_context(): void
    {
        $result = $this->resolver->resolve(
            $this->dto(),
            $this->meta([
                'visibility_level' => TemplateVisibilityLevel::Module->value,
                'study_type_id'    => 'st-1',
                'study_id'         => 's-1',
                'module_id'        => 'm-1',
            ]),
        );

        $this->assertSame(['studyTypeId' => 'st-1', 'studyId' => 's-1', 'moduleId' => 'm-1', 'teamId' => null], $result);
    }

    public function test_module_visibility_throws_if_dto_has_team(): void
    {
        $this->expectException(ValidationException::class);

        $this->resolver->resolve(
            $this->dto(['teamId' => 'team-x']),
            $this->meta(['visibility_level' => TemplateVisibilityLevel::Module->value, 'module_id' => 'm-1']),
        );
    }

    public function test_module_visibility_throws_if_template_has_no_module(): void
    {
        $this->expectException(ValidationException::class);

        $this->resolver->resolve(
            $this->dto(),
            $this->meta(['visibility_level' => TemplateVisibilityLevel::Module->value, 'module_id' => null]),
        );
    }

    // ── Study ──────────────────────────────────────────────────────────────

    public function test_study_visibility_returns_template_study_no_module(): void
    {
        $result = $this->resolver->resolve(
            $this->dto(),
            $this->meta([
                'visibility_level' => TemplateVisibilityLevel::Study->value,
                'study_type_id'    => 'st-1',
                'study_id'         => 's-1',
            ]),
        );

        $this->assertSame(['studyTypeId' => 'st-1', 'studyId' => 's-1', 'moduleId' => null, 'teamId' => null], $result);
    }

    public function test_study_visibility_throws_if_dto_has_team(): void
    {
        $this->expectException(ValidationException::class);

        $this->resolver->resolve(
            $this->dto(['teamId' => 'team-x']),
            $this->meta(['visibility_level' => TemplateVisibilityLevel::Study->value, 'study_id' => 's-1']),
        );
    }

    public function test_study_visibility_throws_on_mismatched_study(): void
    {
        $this->expectException(ValidationException::class);

        $this->resolver->resolve(
            $this->dto(['studyId' => 's-different']),
            $this->meta(['visibility_level' => TemplateVisibilityLevel::Study->value, 'study_id' => 's-1']),
        );
    }

    public function test_study_visibility_with_valid_module_returns_module_id(): void
    {
        $this->seedModule('m-1', 's-1');

        $result = $this->resolver->resolve(
            $this->dto(['moduleId' => 'm-1']),
            $this->meta(['visibility_level' => TemplateVisibilityLevel::Study->value, 'study_type_id' => 'st-1', 'study_id' => 's-1']),
        );

        $this->assertSame('m-1', $result['moduleId']);
        $this->assertSame('s-1', $result['studyId']);
    }

    public function test_study_visibility_throws_if_module_belongs_to_different_study(): void
    {
        $this->seedModule('m-other', 's-other');

        $this->expectException(ValidationException::class);

        $this->resolver->resolve(
            $this->dto(['moduleId' => 'm-other']),
            $this->meta(['visibility_level' => TemplateVisibilityLevel::Study->value, 'study_id' => 's-1']),
        );
    }

    // ── StudyType ──────────────────────────────────────────────────────────

    public function test_study_type_visibility_no_module_no_study_returns_study_type(): void
    {
        $result = $this->resolver->resolve(
            $this->dto(),
            $this->meta(['visibility_level' => TemplateVisibilityLevel::StudyType->value, 'study_type_id' => 'st-1']),
        );

        $this->assertSame(['studyTypeId' => 'st-1', 'studyId' => null, 'moduleId' => null, 'teamId' => null], $result);
    }

    public function test_study_type_visibility_throws_if_dto_has_team(): void
    {
        $this->expectException(ValidationException::class);

        $this->resolver->resolve(
            $this->dto(['teamId' => 't-x']),
            $this->meta(['visibility_level' => TemplateVisibilityLevel::StudyType->value, 'study_type_id' => 'st-1']),
        );
    }

    public function test_study_type_visibility_with_module_resolves_study_from_db(): void
    {
        $this->seedModule('m-1', 's-1');

        DB::table('study_types')->insertOrIgnore(['id' => 'st-1', 'name' => 'Grado']);

        $result = $this->resolver->resolve(
            $this->dto(['moduleId' => 'm-1']),
            $this->meta(['visibility_level' => TemplateVisibilityLevel::StudyType->value, 'study_type_id' => 'st-1']),
        );

        $this->assertSame('st-1', $result['studyTypeId']);
        $this->assertSame('s-1', $result['studyId']);
        $this->assertSame('m-1', $result['moduleId']);
    }

    // ── Global ─────────────────────────────────────────────────────────────

    public function test_global_visibility_with_study_type_only_returns_study_type(): void
    {
        // studyTypeId alone does not trigger a DB lookup in resolveGlobalContext.
        $result = $this->resolver->resolve(
            $this->dto(['studyTypeId' => 'st-1']),
            $this->meta(['visibility_level' => TemplateVisibilityLevel::Global->value]),
        );

        $this->assertNull($result['teamId']);
        $this->assertSame('st-1', $result['studyTypeId']);
        $this->assertNull($result['studyId']);
    }

    public function test_global_visibility_with_no_context_returns_all_null(): void
    {
        $result = $this->resolver->resolve(
            $this->dto(),
            $this->meta(['visibility_level' => TemplateVisibilityLevel::Global->value]),
        );

        $this->assertSame(['studyTypeId' => null, 'studyId' => null, 'moduleId' => null, 'teamId' => null], $result);
    }

    public function test_global_visibility_with_seeded_study_returns_study_context(): void
    {
        $this->seedStudy('s-1', 'st-1');

        $result = $this->resolver->resolve(
            $this->dto(['studyId' => 's-1']),
            $this->meta(['visibility_level' => TemplateVisibilityLevel::Global->value]),
        );

        $this->assertSame('s-1', $result['studyId']);
        $this->assertSame('st-1', $result['studyTypeId']);
    }

    public function test_global_visibility_throws_if_both_team_and_academic_context(): void
    {
        $this->expectException(ValidationException::class);

        $this->resolver->resolve(
            $this->dto(['teamId' => 't-1', 'studyTypeId' => 'st-1']),
            $this->meta(['visibility_level' => TemplateVisibilityLevel::Global->value]),
        );
    }

    // ── Fallback ───────────────────────────────────────────────────────────

    public function test_unknown_visibility_falls_through_to_dto_values(): void
    {
        $dto = $this->dto(['studyTypeId' => 'st-x', 'studyId' => 's-x', 'teamId' => 't-x']);

        $result = $this->resolver->resolve($dto, $this->meta(['visibility_level' => 'unknown_level']));

        $this->assertSame('st-x', $result['studyTypeId']);
        $this->assertSame('s-x', $result['studyId']);
        $this->assertSame('t-x', $result['teamId']);
    }
}
