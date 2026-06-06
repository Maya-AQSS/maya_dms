<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\CloneDeadlinePolicy;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class CloneDeadlinePolicyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-06 12:00:00'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_clears_a_past_deadline(): void
    {
        $this->assertNull(CloneDeadlinePolicy::clearIfPast('2025-09-15'));
    }

    public function test_keeps_a_future_deadline(): void
    {
        $this->assertSame('2026-09-15', CloneDeadlinePolicy::clearIfPast('2026-09-15'));
    }

    public function test_keeps_today(): void
    {
        $this->assertSame('2026-06-06', CloneDeadlinePolicy::clearIfPast('2026-06-06'));
    }

    public function test_keeps_null_and_empty(): void
    {
        $this->assertNull(CloneDeadlinePolicy::clearIfPast(null));
        $this->assertSame('', CloneDeadlinePolicy::clearIfPast(''));
    }

    public function test_handles_datetime_instances(): void
    {
        $past = new \DateTimeImmutable('2025-01-01');
        $future = new \DateTimeImmutable('2027-01-01');

        $this->assertNull(CloneDeadlinePolicy::clearIfPast($past));
        $this->assertSame($future, CloneDeadlinePolicy::clearIfPast($future));
    }

    public function test_keeps_unparseable_value_untouched(): void
    {
        $this->assertSame('no-es-fecha', CloneDeadlinePolicy::clearIfPast('no-es-fecha'));
    }
}
