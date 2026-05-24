<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTOs\Users\ReviewerAcademicAssignmentScope;
use App\Repositories\Contracts\AcademicHierarchyRepositoryInterface;
use App\Services\ReviewerAcademicScopeResolver;
use Mockery;
use Tests\TestCase;

final class ReviewerAcademicScopeResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_global_and_personal_return_null_scope(): void
    {
        $resolver = new ReviewerAcademicScopeResolver(Mockery::mock(AcademicHierarchyRepositoryInterface::class));

        $this->assertNull($resolver->resolve('global', null, null, null, null));
        $this->assertNull($resolver->resolve('personal', 'st-1', 's-1', 'm-1', null));
    }

    public function test_study_type_scope_only_includes_study_type_assignment(): void
    {
        $resolver = new ReviewerAcademicScopeResolver(Mockery::mock(AcademicHierarchyRepositoryInterface::class));

        $scope = $resolver->resolve('study_type', 'st-1', null, null, null);

        $this->assertInstanceOf(ReviewerAcademicAssignmentScope::class, $scope);
        $this->assertSame(['st-1'], $scope->studyTypeIds);
        $this->assertSame([], $scope->studyIds);
        $this->assertSame([], $scope->moduleIds);
    }

    public function test_study_scope_includes_study_and_parent_study_type(): void
    {
        $hierarchy = Mockery::mock(AcademicHierarchyRepositoryInterface::class);
        $hierarchy->shouldReceive('findStudyTypeIdByStudyId')->once()->with('s-1')->andReturn('st-1');

        $resolver = new ReviewerAcademicScopeResolver($hierarchy);
        $scope = $resolver->resolve('study', null, 's-1', null, null);

        $this->assertSame(['s-1'], $scope->studyIds);
        $this->assertSame(['st-1'], $scope->studyTypeIds);
        $this->assertSame([], $scope->moduleIds);
    }

    public function test_module_scope_includes_module_study_and_study_type(): void
    {
        $hierarchy = Mockery::mock(AcademicHierarchyRepositoryInterface::class);
        $hierarchy->shouldReceive('findStudyAndTypeByModuleId')->once()->with('m-1')->andReturn([
            'study_id' => 's-1',
            'study_type_id' => 'st-1',
        ]);

        $resolver = new ReviewerAcademicScopeResolver($hierarchy);
        $scope = $resolver->resolve('module', null, null, 'm-1', null);

        $this->assertSame(['m-1'], $scope->moduleIds);
        $this->assertSame(['s-1'], $scope->studyIds);
        $this->assertSame(['st-1'], $scope->studyTypeIds);
    }

    public function test_team_scope_only_includes_team_membership(): void
    {
        $resolver = new ReviewerAcademicScopeResolver(Mockery::mock(AcademicHierarchyRepositoryInterface::class));

        $scope = $resolver->resolve('team', null, null, null, 'team-1');

        $this->assertSame(['team-1'], $scope->teamIds);
        $this->assertSame([], $scope->studyIds);
    }
}
