<?php

declare(strict_types=1);

use App\DTOs\Documents\DocumentAcademicListFilter;
use App\DTOs\Documents\DocumentFilterDto;
use App\DTOs\Users\JwtProfileDto;
use App\DTOs\Users\UserProfileDto;
use App\Services\Contracts\UserProfileServiceInterface;
use App\Support\DocumentAcademicListFilterResolver;

it('resolves cascade filter from explicit singular params', function () {
    $resolver = new DocumentAcademicListFilterResolver(
        Mockery::mock(UserProfileServiceInterface::class),
    );

    $filter = new DocumentFilterDto(
        studyTypeId: 'type-a',
        studyId: 'study-a',
        moduleId: 'mod-a',
    );

    $resolved = $resolver->resolve($filter, 'user-1', JwtProfileDto::fromArray(['id' => 'user-1']));

    expect($resolved)->not->toBeNull()
        ->and($resolved->mode)->toBe(DocumentAcademicListFilter::MODE_CASCADE)
        ->and($resolved->studyTypeIds)->toBe(['type-a'])
        ->and($resolved->studyIds)->toBe(['study-a'])
        ->and($resolved->moduleIds)->toBe(['mod-a']);
});

it('resolves cascade filter with whereIn when one dimension has multiple ids', function () {
    $resolver = new DocumentAcademicListFilterResolver(
        Mockery::mock(UserProfileServiceInterface::class),
    );

    $filter = new DocumentFilterDto(
        studyTypeIds: ['type-a', 'type-b'],
    );

    $resolved = $resolver->resolve($filter, 'user-1', JwtProfileDto::fromArray(['id' => 'user-1']));

    expect($resolved?->mode)->toBe(DocumentAcademicListFilter::MODE_CASCADE)
        ->and($resolved?->studyTypeIds)->toBe(['type-a', 'type-b']);
});

it('resolves union filter from profile with multiple academic scopes', function () {
    $profileService = Mockery::mock(UserProfileServiceInterface::class);
    $profileService->shouldReceive('getProfile')
        ->once()
        ->andReturn(UserProfileDto::fromArray([
            'id' => 'user-1',
            'email' => null,
            'name' => null,
            'department' => null,
            'locale' => 'es',
            'study_type_ids' => ['type-a'],
            'study_ids' => [],
            'module_ids' => ['mod-a', 'mod-b'],
            'team_ids' => [],
            'permissions' => [],
            'source' => 'fdw',
        ]));

    $resolver = new DocumentAcademicListFilterResolver($profileService);

    $filter = new DocumentFilterDto(profileAcademicDefault: true);

    $resolved = $resolver->resolve($filter, 'user-1', JwtProfileDto::fromArray(['id' => 'user-1']));

    expect($resolved?->mode)->toBe(DocumentAcademicListFilter::MODE_UNION)
        ->and($resolved?->studyTypeIds)->toBe(['type-a'])
        ->and($resolved?->moduleIds)->toBe(['mod-a', 'mod-b']);
});

it('resolves single profile scope as cascade filter', function () {
    $profileService = Mockery::mock(UserProfileServiceInterface::class);
    $profileService->shouldReceive('getProfile')
        ->once()
        ->andReturn(UserProfileDto::fromArray([
            'id' => 'user-1',
            'email' => null,
            'name' => null,
            'department' => null,
            'locale' => 'es',
            'study_type_ids' => [],
            'study_ids' => [],
            'module_ids' => ['mod-a'],
            'team_ids' => [],
            'permissions' => [],
            'source' => 'fdw',
        ]));

    $resolver = new DocumentAcademicListFilterResolver($profileService);

    $filter = new DocumentFilterDto(profileAcademicDefault: true);

    $resolved = $resolver->resolve($filter, 'user-1', JwtProfileDto::fromArray(['id' => 'user-1']));

    expect($resolved?->mode)->toBe(DocumentAcademicListFilter::MODE_CASCADE)
        ->and($resolved?->moduleIds)->toBe(['mod-a']);
});

it('returns null when profile academic default has empty scopes', function () {
    $profileService = Mockery::mock(UserProfileServiceInterface::class);
    $profileService->shouldReceive('getProfile')
        ->once()
        ->andReturn(UserProfileDto::fromArray([
            'id' => 'user-1',
            'email' => null,
            'name' => null,
            'department' => null,
            'locale' => 'es',
            'study_type_ids' => [],
            'study_ids' => [],
            'module_ids' => [],
            'team_ids' => [],
            'permissions' => [],
            'source' => 'fdw',
        ]));

    $resolver = new DocumentAcademicListFilterResolver($profileService);

    $filter = new DocumentFilterDto(profileAcademicDefault: true);

    expect($resolver->resolve($filter, 'user-1', JwtProfileDto::fromArray(['id' => 'user-1'])))->toBeNull();
});
