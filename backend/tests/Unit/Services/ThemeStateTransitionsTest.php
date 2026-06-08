<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\ThemeStatus;
use App\Services\ThemeStateTransitions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ThemeStateTransitionsTest extends TestCase
{
    public function test_allows_draft_to_published(): void
    {
        $this->assertTrue(
            ThemeStateTransitions::canTransition(ThemeStatus::Draft, ThemeStatus::Published)
        );
    }

    public function test_allows_published_to_archived(): void
    {
        $this->assertTrue(
            ThemeStateTransitions::canTransition(ThemeStatus::Published, ThemeStatus::Archived)
        );
    }

    public function test_forbids_published_to_draft(): void
    {
        $this->assertFalse(
            ThemeStateTransitions::canTransition(ThemeStatus::Published, ThemeStatus::Draft)
        );
    }

    public function test_forbids_draft_to_archived(): void
    {
        $this->assertFalse(
            ThemeStateTransitions::canTransition(ThemeStatus::Draft, ThemeStatus::Archived)
        );
    }

    public function test_archived_is_terminal(): void
    {
        $this->assertFalse(
            ThemeStateTransitions::canTransition(ThemeStatus::Archived, ThemeStatus::Published)
        );
        $this->assertFalse(
            ThemeStateTransitions::canTransition(ThemeStatus::Archived, ThemeStatus::Draft)
        );
    }

    public function test_assert_throws_validation_exception_on_forbidden_transition(): void
    {
        $this->expectException(ValidationException::class);

        ThemeStateTransitions::assert(ThemeStatus::Published, ThemeStatus::Draft);
    }

    public function test_assert_passes_on_allowed_transition(): void
    {
        ThemeStateTransitions::assert(ThemeStatus::Draft, ThemeStatus::Published);

        $this->expectNotToPerformAssertions();
    }
}
