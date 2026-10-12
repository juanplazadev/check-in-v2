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
 * Pushes an appointment's status or time change to the open dashboards of
 * staff who work its location, so it moves on or leaves the live queue's
 * arriving rail.
 */
final class AppointmentUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public readonly Appointment $appointment) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return BroadcastChannels::appointmentsAt($this->appointment->location_id);
    }

    public function broadcastAs(): string
    {
        return 'appointment.updated';
    }

    /**
     * @return array{appointment: array<string, mixed>}
     */
    public function broadcastWith(): array
    {
        return ['appointment' => resolve(AppointmentRecordResolver::class)->record($this->appointment)];
    }
}
