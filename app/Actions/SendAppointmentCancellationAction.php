<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Appointment;
use App\Models\User;
use App\Notifications\AppointmentCancellation;

final readonly class SendAppointmentCancellationAction
{
    /**
     * Send the driver confirmation that their booking was cancelled.
     *
     * Unlike {@see SendAppointmentCopyAction} this does not check
     * notify_appointment_copy. That preference covers the copies we send off
     * the back of a booking; this is the receipt for an irreversible action the
     * driver just took, and a driver who has copies switched off still needs
     * proof the slot was released. The channel preference is still honoured,
     * through notificationChannels(): they choose how we reach them, not
     * whether they hear about this.
     *
     * Guests are skipped for the same reason as everywhere else - no account,
     * nowhere to notify.
     */
    public function handle(Appointment $appointment): void
    {
        $appointment->loadMissing('user');

        $user = $appointment->user;

        if (! $user instanceof User) {
            return;
        }

        $user->notify(new AppointmentCancellation($appointment)->locale($appointment->locale));
    }
}
