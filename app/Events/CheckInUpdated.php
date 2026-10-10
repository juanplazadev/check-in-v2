<?php

declare(strict_types=1);

namespace App\Events;

use App\Dashboard\BroadcastChannels;
use App\Dashboard\CheckInRecordResolver;
use App\Models\CheckIn;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Pushes a check-in's status or ERP status change to the open dashboards of
 * staff who work its location, so a card leaves the live queue and table rows
 * stay current.
 */
final class CheckInUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public readonly CheckIn $checkIn) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return BroadcastChannels::checkInsAt($this->checkIn->location_id);
    }

    public function broadcastAs(): string
    {
        return 'check-in.updated';
    }

    /**
     * @return array{checkIn: array<string, mixed>}
     */
    public function broadcastWith(): array
    {
        return ['checkIn' => resolve(CheckInRecordResolver::class)->record($this->checkIn)];
    }
}
