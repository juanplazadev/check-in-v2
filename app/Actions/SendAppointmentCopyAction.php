<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Appointment;
use App\Models\User;
use App\Notifications\AppointmentCopy;
use Illuminate\Support\Facades\Notification;

final readonly class SendAppointmentCopyAction
{
    /**
     * Send the driver their own copy of a booking. See
     * {@see SendCheckInCopyAction} for why a guest always gets a text and has
     * no preference to consult.
     */
    public function handle(Appointment $appointment): void
    {
        $appointment->loadMissing('user');

        $user = $appointment->user;

        $notification = new AppointmentCopy($appointment)->locale($appointment->locale);

        if ($user instanceof User) {
            if ($user->notify_appointment_copy) {
                $user->notify($notification);
            }

            return;
        }

        Notification::route('sms', $appointment->drivers_cellphone)->notify($notification);
    }
}
