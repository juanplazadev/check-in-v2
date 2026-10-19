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
 * Tells the open dashboards of staff who work the location that an
 * appointment was permanently deleted, so its row can go. Scalars only: the
 * row is gone, so a serialized model could not be restored on the queue.
 */
final class AppointmentDeleted implements ShouldBroadcast, ShouldDispatchAfterCommit
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
        return BroadcastChannels::appointmentsAt($this->locationId);
    }

    public function broadcastAs(): string
    {
        return 'appointment.deleted';
    }

    /**
     * @return array{uuid: string}
     */
    public function broadcastWith(): array
    {
        return ['uuid' => $this->uuid];
    }
}
