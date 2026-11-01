<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Appointment;
use App\Models\User;
use App\Notifications\AppointmentRescheduled;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Notification;

final readonly class SendAppointmentRescheduledAction
{
    /**
     * Tell the driver their booking has a new time.
     *
     * Like {@see SendAppointmentCancellationAction} this does not check
     * notify_appointment_copy: a driver who has copies switched off still has
     * to know when to show up. The channel preference is still honoured. A
     * guest gets a text, as in {@see SendAppointmentCopyAction}.
     */
    public function handle(Appointment $appointment, CarbonInterface $previousStart): void
    {
        $appointment->loadMissing('user');

        $notification = new AppointmentRescheduled($appointment, $previousStart)->locale($appointment->locale);

        if ($appointment->user instanceof User) {
            $appointment->user->notify($notification);

            return;
        }

        Notification::route('sms', $appointment->drivers_cellphone)->notify($notification);
    }
}
