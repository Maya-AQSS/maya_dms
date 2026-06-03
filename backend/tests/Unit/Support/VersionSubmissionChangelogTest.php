<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\VersionSubmissionChangelog;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VersionSubmissionChangelogTest extends TestCase
{
    public function test_require_non_empty_uses_head_when_explicit_missing(): void
    {
        $this->assertSame(
            'Desde head',
            VersionSubmissionChangelog::requireNonEmpty(null, '  Desde head  '),
        );
    }

    public function test_require_non_empty_throws_when_both_empty(): void
    {
        $this->expectException(ValidationException::class);

        VersionSubmissionChangelog::requireNonEmpty('', null);
    }

    public function test_for_api_exposure_only_in_working_statuses(): void
    {
        $this->assertSame('Nota', VersionSubmissionChangelog::forApiExposure('in_review', 'Nota'));
        $this->assertNull(VersionSubmissionChangelog::forApiExposure('published', 'Nota'));
    }
}
