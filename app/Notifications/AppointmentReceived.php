<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * A new appointment booking, as it appears in a staff member's dashboard
 * notification bell. See {@see CheckInReceived} for why it is stored and
 * broadcast, and why it stays out of the record trail.
 */
final class AppointmentReceived extends Notification implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * Stored in the `type` column, which the bell switches on. Mirrored in
     * NotificationsMenu.tsx.
     */
    public const string TYPE = 'appointment.booked';

    public function __construct(public Appointment $appointment) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function databaseType(User $notifiable): string
    {
        return self::TYPE;
    }

    /**
     * The slot is formatted here, in the location's timezone, because that is
     * the time the driver booked; the browser would shift it to its own zone.
     *
     * @return array{appointmentUuid: string, referenceNumber: string, driverName: string, locationName: string, scheduledFor: string}
     */
    public function toArray(User $notifiable): array
    {
        $this->appointment->loadMissing('location');

        $location = $this->appointment->location;

        return [
            'appointmentUuid' => $this->appointment->uuid,
            'referenceNumber' => $this->appointment->reference_number,
            'driverName' => $this->appointment->drivers_name,
            'locationName' => $location->name,
            'scheduledFor' => $this->appointment->scheduled_for
                ->setTimezone($location->timezone)
                ->format('M j, g:i A'),
        ];
    }

    public function toBroadcast(User $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
