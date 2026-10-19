<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Mail\RecordDocumentMail;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Emails an appointment's PDF to the driver and logs it on both the
 * appointment's timeline and the staff member's own account trail.
 *
 * An appointment stores no email of its own, so only a driver with an account
 * can be reached, at their account address. The address is never taken from
 * the request. The mail is queued after the commit, so a failed history write
 * sends nothing, and renders in the locale captured at booking. Both logs keep
 * the status at send time, since the PDF prints it and staff need to know which
 * one went out.
 */
final readonly class EmailAppointmentPdfAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @throws ValidationException when the driver has no account email.
     * @throws Throwable
     */
    public function handle(Appointment $appointment, User $staff): void
    {
        $recipient = $appointment->user?->email;

        if ($recipient === null || $recipient === '') {
            throw ValidationException::withMessages([
                'email' => 'This driver has no email on file.',
            ]);
        }

        DB::transaction(function () use ($appointment, $staff, $recipient): void {
            Mail::to($recipient)->queue(
                new RecordDocumentMail($appointment)
                    ->onQueue('pdf')
                    ->locale($appointment->locale)
                    ->afterCommit(),
            );

            $this->recordHistory->handle($appointment, RecordHistoryEvent::PdfEmailed, channel: 'mail', user: $staff, context: [
                'recipient' => $recipient,
                'status' => $appointment->status->value,
            ]);

            $this->recordUserHistory->handle($staff, UserHistoryEvent::AppointmentPdfEmailed, [
                'appointment' => $appointment->uuid,
                'recipient' => $recipient,
                'status' => $appointment->status->value,
            ]);
        });
    }
}
