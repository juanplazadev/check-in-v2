<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Appointment;
use App\Models\User;
use App\Notifications\AppointmentReminder;
use Illuminate\Support\Facades\Date;

final readonly class SendAppointmentReminderAction
{
    /**
     * Send the driver their day-before reminder, if they still want one.
     *
     * Unlike {@see SendAppointmentCancellationAction} this does honour the
     * preference: a reminder is a courtesy the driver opted into, not a receipt
     * for something they just did. Guests have no account and no preference, so
     * they are skipped - see the query, which never selects them anyway.
     *
     * Returns whether anything was sent, so the command can report a count.
     */
    public function handle(Appointment $appointment): bool
    {
        $appointment->loadMissing('user');

        $user = $appointment->user;

        if (! $user instanceof User || ! $user->notify_appointment_reminder) {
            return false;
        }

        $user->notify(new AppointmentReminder($appointment)->locale($appointment->locale));

        /*
         * Stamped now, when the notification is queued, rather than when the
         * worker actually sends it. A job that later fails will still read as
         * reminded, which is the deliberate trade: stamping from the
         * NotificationSent event would reopen the double-send window this
         * column exists to close, because the hourly sweep would find the row
         * unstamped for as long as the queue is backed up.
         *
         * Only status/erp_status are watched by the history hook in
         * AppServiceProvider, so this save writes no trail row of its own - the
         * trail entry comes from RecordNotificationHistory once the message
         * actually goes out.
         */
        $appointment->forceFill(['reminder_sent_at' => Date::now()])->save();

        return true;
    }
}
