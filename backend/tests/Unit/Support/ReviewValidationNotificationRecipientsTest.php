<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\ReviewValidationNotificationRecipients;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReviewValidationNotificationRecipientsTest extends TestCase
{
    #[Test]
    public function sequential_returns_only_lowest_pending_stage(): void
    {
        $pending = [
            ['reviewer_id' => 'a', 'stage' => 1],
            ['reviewer_id' => 'b', 'stage' => 2],
            ['reviewer_id' => 'c', 'stage' => 2],
        ];

        $result = ReviewValidationNotificationRecipients::filterForReviewMode('sequential', $pending);

        $this->assertCount(1, $result);
        $this->assertSame('a', $result[0]['reviewer_id']);
    }

    #[Test]
    public function parallel_returns_all_pending(): void
    {
        $pending = [
            ['reviewer_id' => 'a', 'stage' => 1],
            ['reviewer_id' => 'b', 'stage' => 2],
        ];

        $result = ReviewValidationNotificationRecipients::filterForReviewMode('parallel', $pending);

        $this->assertCount(2, $result);
    }
}
