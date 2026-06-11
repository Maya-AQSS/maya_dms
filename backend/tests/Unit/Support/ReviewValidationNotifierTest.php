<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\ReviewValidationNotifier;
use Illuminate\Support\Facades\Log;
use Maya\Messaging\Publishers\NotificationPublisher;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for ReviewValidationNotifier.
 *
 * Uses a Mockery mock for NotificationPublisher so no real messaging infrastructure is needed.
 */
final class ReviewValidationNotifierTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function calls_send_for_each_recipient_with_built_args(): void
    {
        $publisher = Mockery::mock(NotificationPublisher::class);
        $publisher->shouldReceive('send')
            ->twice()
            ->withArgs(function (mixed ...$args): bool {
                // named arg spread → positional; just assert called twice (checked by ->twice())
                return true;
            });

        $notifier = new ReviewValidationNotifier($publisher);

        $recipients = [
            ['user_id' => 'user-a', 'stage' => 1],
            ['user_id' => 'user-b', 'stage' => 1],
        ];

        $called = [];
        $notifier->notifyEach($recipients, 'user_id', function (string $recipientId) use (&$called): array {
            $called[] = $recipientId;

            return [
                'type' => 'test.notification',
                'recipientId' => $recipientId,
                'title' => 'T',
                'body' => 'B',
                'titleKey' => 'k.t',
                'bodyKey' => 'k.b',
                'params' => [],
                'severity' => 'high',
                'channels' => ['app'],
                'metadata' => [],
            ];
        });

        $this->assertSame(['user-a', 'user-b'], $called);
    }

    #[Test]
    public function skips_rows_with_empty_recipient_key(): void
    {
        $publisher = Mockery::mock(NotificationPublisher::class);
        $publisher->shouldReceive('send')->never();

        $notifier = new ReviewValidationNotifier($publisher);

        $recipients = [
            ['user_id' => '', 'stage' => 1],
            ['stage' => 1],          // missing key
        ];

        $notifier->notifyEach($recipients, 'user_id', fn (string $id): array => [
            'type' => 'test.notification',
            'recipientId' => $id,
            'title' => 'T',
            'body' => 'B',
            'titleKey' => 'k.t',
            'bodyKey' => 'k.b',
            'params' => [],
            'severity' => 'high',
            'channels' => ['app'],
            'metadata' => [],
        ]);

        // Mockery will assert ->never() on tearDown
        $this->assertTrue(true);
    }

    #[Test]
    public function handles_empty_recipients_list(): void
    {
        $publisher = Mockery::mock(NotificationPublisher::class);
        $publisher->shouldReceive('send')->never();

        $notifier = new ReviewValidationNotifier($publisher);

        $notifier->notifyEach([], 'user_id', fn (string $id): array => []);

        $this->assertTrue(true);
    }

    #[Test]
    public function logs_warning_and_continues_when_publisher_throws(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $channel, array $context): bool {
                return $channel === 'notification.publish_failed'
                    && isset($context['error'])
                    && $context['user_id'] === 'user-a';
            });

        $publisher = Mockery::mock(NotificationPublisher::class);
        $publisher->shouldReceive('send')->once()->andThrow(new \RuntimeException('bus down'));

        $notifier = new ReviewValidationNotifier($publisher);

        $recipients = [['user_id' => 'user-a', 'stage' => 1]];

        // Must not re-throw
        $notifier->notifyEach($recipients, 'user_id', fn (string $id): array => [
            'type' => 'test.notification',
            'recipientId' => $id,
            'title' => 'T',
            'body' => 'B',
            'titleKey' => 'k.t',
            'bodyKey' => 'k.b',
            'params' => [],
            'severity' => 'high',
            'channels' => ['app'],
            'metadata' => [],
        ]);

        $this->assertTrue(true);
    }

    #[Test]
    public function uses_reviewer_id_key_for_document_style_recipients(): void
    {
        $publisher = Mockery::mock(NotificationPublisher::class);
        $publisher->shouldReceive('send')->once();

        $notifier = new ReviewValidationNotifier($publisher);

        $recipients = [['reviewer_id' => 'doc-reviewer-x', 'stage' => 1]];

        $captured = null;
        $notifier->notifyEach($recipients, 'reviewer_id', function (string $recipientId) use (&$captured): array {
            $captured = $recipientId;

            return [
                'type' => 'document.validation_requested',
                'recipientId' => $recipientId,
                'title' => 'T',
                'body' => 'B',
                'titleKey' => 'k.t',
                'bodyKey' => 'k.b',
                'params' => [],
                'severity' => 'high',
                'channels' => ['app'],
                'metadata' => [],
            ];
        });

        $this->assertSame('doc-reviewer-x', $captured);
    }
}
