<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RecordHistoryEvent;
use App\Mail\AppointmentCancelled;
use App\Models\Appointment;
use Illuminate\Support\Facades\Mail;

final readonly class SendAppointmentCancelledEmailAction
{
    public function __construct(private RecordHistoryAction $history) {}

    /**
     * Queue the shipping-department notice that a slot has been given back.
     * Recipients and the silent skip work exactly as in
     * {@see SendAppointmentBookedEmailAction}.
     */
    public function handle(Appointment $appointment): void
    {
        $appointment->loadMissing(['location.address', 'purchaseOrders']);

        $recipients = $appointment->location->appointmentEmailAddresses();

        if ($recipients === []) {
            return;
        }

        Mail::to($recipients)->send(new AppointmentCancelled($appointment));

        // A Mailable, so the notification listener never sees it. See
        // SendCheckInCompletedEmailAction for the full note.
        $this->history->handle(
            record: $appointment,
            event: RecordHistoryEvent::EmployeeNotificationQueued,
            subject: 'appointment-cancelled',
            channel: 'mail',
            context: ['recipients' => $recipients],
        );
    }
}
