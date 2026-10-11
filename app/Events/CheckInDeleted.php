<?php

declare(strict_types=1);

namespace App\Events;

use App\Dashboard\BroadcastChannels;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Tells the open dashboards of staff who work the location that a check-in
 * was permanently deleted, so its card and table row can go. Scalars only:
 * the row is gone, so a serialized model could not be restored on the queue.
 */
final class CheckInDeleted implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly string $uuid,
        public readonly int $locationId,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return BroadcastChannels::checkInsAt($this->locationId);
    }

    public function broadcastAs(): string
    {
        return 'check-in.deleted';
    }

    /**
     * @return array{uuid: string}
     */
    public function broadcastWith(): array
    {
        return ['uuid' => $this->uuid];
    }
}
