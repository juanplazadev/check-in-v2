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
 * Pushes a new check-in to every open staff dashboard.
 */
final class CheckInCreated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public readonly CheckIn $checkIn) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel(BroadcastChannels::CHECK_INS);
    }

    public function broadcastAs(): string
    {
        return 'check-in.created';
    }

    /**
     * @return array{checkIn: array<string, mixed>}
     */
    public function broadcastWith(): array
    {
        return ['checkIn' => resolve(CheckInRecordResolver::class)->record($this->checkIn)];
    }
}
