<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class NotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $recipientId,
        public readonly string $app,
        public readonly string $type,
        public readonly string $title,
        public readonly string $body,
        public readonly array $metadata = [],
        public readonly bool $isCritical = false,
        public readonly string $scope = 'user',
    ) {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('notifications.'.$this->recipientId);
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        return [
            'app' => $this->app,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'metadata' => $this->metadata,
            'is_critical' => $this->isCritical,
            'scope' => $this->scope,
        ];
    }
}
