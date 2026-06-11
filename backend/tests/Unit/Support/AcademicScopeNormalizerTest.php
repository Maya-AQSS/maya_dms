<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Enums\TemplateVisibilityLevel;
use App\Repositories\Contracts\AcademicHierarchyRepositoryInterface;
use App\Support\AcademicScopeNormalizer;
use App\Support\AcademicScopeContext;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for AcademicScopeNormalizer::normalize().
 *
 * The method performs two roles:
 *   1. Nullify out-of-scope academic fields (Personal, Team).
 *   2. Validate and pin academic fields according to the template's scope.
 *
 * Each test instantiates a context that mimics either the Template domain
 * (strict — always writes template IDs) or the Document domain (lenient —
 * only writes template IDs when they are non-null).
 */
final class AcademicScopeNormalizerTest extends TestCase
{
    private AcademicHierarchyRepositoryInterface $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = Mockery::mock(AcademicHierarchyRepositoryInterface::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── Personal / Team (100% identical across domains) ───────────────────────

    public function test_personal_nullifies_all_academic_fields(): void
    {
        $ctx = $this->makeContext(
            level: TemplateVisibilityLevel::Personal,
            templateStudyTypeId: null,
            templateStudyId: null,
            templateModuleId: null,
        );
        $attrs = ['name' => 'Doc', 'study_type_id' => 'st-1', 'study_id' => 's-1', 'module_id' => 'm-1'];

        $result = AcademicScopeNormalizer::normalize($this->repo, $ctx, $attrs);

        $this->assertNull($result['study_type_id']);
        $this->assertNull($result['study_id']);
        $this->assertNull($result['module_id']);
        $this->assertSame('Doc', $result['name']);
    }

    public function test_team_pins_academic_fields_to_template_values(): void
    {
        $ctx = $this->makeContext(
            level: TemplateVisibilityLevel::Team,
            templateStudyTypeId: 'st-A',
            templateStudyId: 's-A',
            templateModuleId: 'm-A',
        );
        $attrs = ['study_type_id' => 'st-other', 'study_id' => 's-other', 'module_id' => 'm-other'];

        $result = AcademicScopeNormalizer::normalize($this->repo, $ctx, $attrs);

        $this->assertSame('st-A', $result['study_type_id']);
        $this->assertSame('s-A', $result['study_id']);
        $this->assertSame('m-A', $result['module_id']);
    }

    public function test_personal_preserves_non_academic_keys(): void
    {
        $ctx = $this->makeContext(TemplateVisibilityLevel::Personal);
        $attrs = ['title' => 'My doc', 'study_id' => 's-1'];

        $result = AcademicScopeNormalizer::normalize($this->repo, $ctx, $attrs);

        $this->assertSame('My doc', $result['title']);
    }

    // ── Module ────────────────────────────────────────────────────────────────

    public function test_module_pins_all_to_template_when_template_module_non_null(): void
    {
        $ctx = $this->makeContext(
            level: TemplateVisibilityLevel::Module,
            templateStudyTypeId: 'st-1',
            templateStudyId: 's-1',
            templateModuleId: 'm-1',
        );
        $attrs = ['module_id' => 'm-1', 'study_id' => null];

        $result = AcademicScopeNormalizer::normalize($this->repo, $ctx, $attrs);

        $this->assertSame('m-1', $result['module_id']);
        $this->assertSame('s-1', $result['study_id']);
        $this->assertSame('st-1', $result['study_type_id']);
    }

    public function test_module_throws_when_incoming_module_differs_from_template(): void
    {
        $ctx = $this->makeContext(
            level: TemplateVisibilityLevel::Module,
            templateStudyTypeId: 'st-1',
            templateStudyId: 's-1',
            templateModuleId: 'm-1',
            onModuleConflict: 'El módulo no puede cambiar.',
        );
        $attrs = ['module_id' => 'm-OTHER'];

        $this->expectException(ValidationException::class);
        AcademicScopeNormalizer::normalize($this->repo, $ctx, $attrs);
    }

    public function test_module_allows_null_incoming_module(): void
    {
        $ctx = $this->makeContext(
            level: TemplateVisibilityLevel::Module,
            templateStudyTypeId: 'st-1',
            templateStudyId: 's-1',
            templateModuleId: 'm-1',
        );
        $attrs = ['module_id' => null];

        // Should not throw: null means "not provided", template value wins.
        $result = AcademicScopeNormalizer::normalize($this->repo, $ctx, $attrs);

        $this->assertSame('m-1', $result['module_id']);
    }

    // ── Study ─────────────────────────────────────────────────────────────────

    public function test_study_throws_when_incoming_study_differs(): void
    {
        $ctx = $this->makeContext(
            level: TemplateVisibilityLevel::Study,
            templateStudyTypeId: 'st-1',
            templateStudyId: 's-TEMPLATE',
            templateModuleId: null,
            onStudyConflict: 'El estudio no puede cambiar.',
        );
        $attrs = ['study_id' => 's-OTHER'];

        $this->expectException(ValidationException::class);
        AcademicScopeNormalizer::normalize($this->repo, $ctx, $attrs);
    }

    public function test_study_accepts_matching_study_id(): void
    {
        $ctx = $this->makeContext(
            level: TemplateVisibilityLevel::Study,
            templateStudyTypeId: 'st-1',
            templateStudyId: 's-1',
            templateModuleId: null,
        );
        $attrs = ['study_id' => 's-1', 'module_id' => ''];

        $result = AcademicScopeNormalizer::normalize($this->repo, $ctx, $attrs);

        $this->assertSame('s-1', $result['study_id']);
        $this->assertSame('st-1', $result['study_type_id']);
    }

    public function test_study_validates_module_belongs_to_study(): void
    {
        $this->repo->shouldReceive('findStudyIdByModuleId')
            ->with('m-1')
            ->andReturn('s-WRONG');

        $ctx = $this->makeContext(
            level: TemplateVisibilityLevel::Study,
            templateStudyTypeId: 'st-1',
            templateStudyId: 's-1',
            templateModuleId: null,
            onModuleStudyMismatch: 'El módulo no pertenece al estudio.',
        );
        $attrs = ['study_id' => 's-1', 'module_id' => 'm-1'];

        $this->expectException(ValidationException::class);
        AcademicScopeNormalizer::normalize($this->repo, $ctx, $attrs);
    }

    public function test_study_accepts_valid_module_in_study(): void
    {
        $this->repo->shouldReceive('findStudyIdByModuleId')
            ->with('m-1')
            ->andReturn('s-1');

        $ctx = $this->makeContext(
            level: TemplateVisibilityLevel::Study,
            templateStudyTypeId: 'st-1',
            templateStudyId: 's-1',
            templateModuleId: null,
            onModuleStudyMismatch: 'El módulo no pertenece al estudio.',
        );
        $attrs = ['study_id' => 's-1', 'module_id' => 'm-1'];

        $result = AcademicScopeNormalizer::normalize($this->repo, $ctx, $attrs);

        $this->assertSame('m-1', $result['module_id']);
        $this->assertSame('s-1', $result['study_id']);
    }

    // ── StudyType ─────────────────────────────────────────────────────────────

    public function test_study_type_throws_when_module_belongs_to_different_study_type(): void
    {
        $this->repo->shouldReceive('findStudyAndTypeByModuleId')
            ->with('m-1')
            ->andReturn(['study_id' => 's-1', 'study_type_id' => 'st-WRONG']);

        $ctx = $this->makeContext(
            level: TemplateVisibilityLevel::StudyType,
            templateStudyTypeId: 'st-1',
            templateStudyId: null,
            templateModuleId: null,
            onModuleTypeMismatch: 'Módulo fuera del tipo de estudio.',
        );
        $attrs = ['module_id' => 'm-1'];

        $this->expectException(ValidationException::class);
        AcademicScopeNormalizer::normalize($this->repo, $ctx, $attrs);
    }

    public function test_study_type_pins_study_from_module(): void
    {
        $this->repo->shouldReceive('findStudyAndTypeByModuleId')
            ->with('m-1')
            ->andReturn(['study_id' => 's-1', 'study_type_id' => 'st-1']);

        $ctx = $this->makeContext(
            level: TemplateVisibilityLevel::StudyType,
            templateStudyTypeId: 'st-1',
            templateStudyId: null,
            templateModuleId: null,
        );
        $attrs = ['module_id' => 'm-1'];

        $result = AcademicScopeNormalizer::normalize($this->repo, $ctx, $attrs);

        $this->assertSame('m-1', $result['module_id']);
        $this->assertSame('s-1', $result['study_id']);
        $this->assertSame('st-1', $result['study_type_id']);
    }

    public function test_study_type_validates_study_belongs_to_type(): void
    {
        $this->repo->shouldReceive('findStudyTypeIdByStudyId')
            ->with('s-1')
            ->andReturn('st-WRONG');

        $ctx = $this->makeContext(
            level: TemplateVisibilityLevel::StudyType,
            templateStudyTypeId: 'st-1',
            templateStudyId: null,
            templateModuleId: null,
            onStudyTypeMismatch: 'El estudio no pertenece al tipo.',
        );
        $attrs = ['study_id' => 's-1'];

        $this->expectException(ValidationException::class);
        AcademicScopeNormalizer::normalize($this->repo, $ctx, $attrs);
    }

    // ── Global ────────────────────────────────────────────────────────────────

    public function test_global_passes_through_when_no_module(): void
    {
        $ctx = $this->makeContext(TemplateVisibilityLevel::Global);
        $attrs = ['study_id' => 's-1', 'study_type_id' => 'st-1'];

        $result = AcademicScopeNormalizer::normalize($this->repo, $ctx, $attrs);

        $this->assertSame('s-1', $result['study_id']);
    }

    public function test_global_throws_when_module_does_not_exist(): void
    {
        $this->repo->shouldReceive('findStudyIdByModuleId')
            ->with('m-ghost')
            ->andReturn(null);

        $ctx = $this->makeContext(
            level: TemplateVisibilityLevel::Global,
            onModuleNotFound: 'El módulo no existe.',
        );
        $attrs = ['module_id' => 'm-ghost'];

        $this->expectException(ValidationException::class);
        AcademicScopeNormalizer::normalize($this->repo, $ctx, $attrs);
    }

    public function test_global_throws_when_study_mismatches_module(): void
    {
        $this->repo->shouldReceive('findStudyIdByModuleId')
            ->with('m-1')
            ->andReturn('s-1');

        $ctx = $this->makeContext(
            level: TemplateVisibilityLevel::Global,
            onStudyModuleMismatch: 'El estudio no corresponde al módulo.',
        );
        $attrs = ['module_id' => 'm-1', 'study_id' => 's-OTHER'];

        $this->expectException(ValidationException::class);
        AcademicScopeNormalizer::normalize($this->repo, $ctx, $attrs);
    }

    public function test_global_accepts_consistent_module_and_study(): void
    {
        $this->repo->shouldReceive('findStudyIdByModuleId')
            ->with('m-1')
            ->andReturn('s-1');

        $ctx = $this->makeContext(TemplateVisibilityLevel::Global);
        $attrs = ['module_id' => 'm-1', 'study_id' => 's-1'];

        $result = AcademicScopeNormalizer::normalize($this->repo, $ctx, $attrs);

        $this->assertSame('m-1', $result['module_id']);
        $this->assertSame('s-1', $result['study_id']);
    }

    // ── Attributes passthrough — fields not present in attributes ─────────────

    public function test_missing_academic_keys_in_attributes_use_entity_values(): void
    {
        // When neither study_id nor module_id is in $attrs, the entity's current
        // values are used (empty string = no module). Global with no module → passthrough.
        $ctx = $this->makeContext(
            level: TemplateVisibilityLevel::Global,
            entityStudyTypeId: null,
            entityStudyId: null,
            entityModuleId: null,
        );
        $attrs = ['title' => 'Only title changed'];

        $result = AcademicScopeNormalizer::normalize($this->repo, $ctx, $attrs);

        $this->assertSame('Only title changed', $result['title']);
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function makeContext(
        TemplateVisibilityLevel $level = TemplateVisibilityLevel::Global,
        ?string $templateStudyTypeId = null,
        ?string $templateStudyId = null,
        ?string $templateModuleId = null,
        ?string $entityStudyTypeId = null,
        ?string $entityStudyId = null,
        ?string $entityModuleId = null,
        string $onModuleConflict = 'El módulo no puede cambiar.',
        string $onStudyConflict = 'El estudio no puede cambiar.',
        string $onModuleStudyMismatch = 'El módulo no pertenece al estudio.',
        string $onModuleTypeMismatch = 'Módulo fuera del tipo de estudio.',
        string $onStudyTypeMismatch = 'El estudio no pertenece al tipo.',
        string $onModuleNotFound = 'El módulo no existe.',
        string $onStudyModuleMismatch = 'El estudio no corresponde al módulo.',
    ): AcademicScopeContext {
        return new AcademicScopeContext(
            visibilityLevel: $level,
            templateStudyTypeId: $templateStudyTypeId,
            templateStudyId: $templateStudyId,
            templateModuleId: $templateModuleId,
            entityStudyTypeId: $entityStudyTypeId,
            entityStudyId: $entityStudyId,
            entityModuleId: $entityModuleId,
            onModuleConflict: $onModuleConflict,
            onStudyConflict: $onStudyConflict,
            onModuleStudyMismatch: $onModuleStudyMismatch,
            onModuleTypeMismatch: $onModuleTypeMismatch,
            onStudyTypeMismatch: $onStudyTypeMismatch,
            onModuleNotFound: $onModuleNotFound,
            onStudyModuleMismatch: $onStudyModuleMismatch,
        );
    }
}
