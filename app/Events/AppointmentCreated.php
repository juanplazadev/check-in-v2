<?php

declare(strict_types=1);

namespace App\Events;

use App\Dashboard\AppointmentRecordResolver;
use App\Dashboard\BroadcastChannels;
use App\Models\Appointment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Pushes a new booking to every open staff dashboard, so it joins the live
 * queue's arriving rail.
 */
final class AppointmentCreated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public readonly Appointment $appointment) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel(BroadcastChannels::APPOINTMENTS);
    }

    public function broadcastAs(): string
    {
        return 'appointment.created';
    }

    /**
     * @return array{appointment: array<string, mixed>}
     */
    public function broadcastWith(): array
    {
        return ['appointment' => resolve(AppointmentRecordResolver::class)->record($this->appointment)];
    }
}
