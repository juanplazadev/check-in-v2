<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Mail\RecordDocumentMail;
use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Emails a check-in's PDF to the driver, at the address on the check-in, and
 * logs it on both the check-in's timeline and the staff member's own account
 * trail. The address is never taken from the request. Both logs keep the status
 * at send time, since the PDF prints it and staff need to know which one went out.
 *
 * The mail is queued after the commit, so a failed history write sends
 * nothing. It renders in the locale captured at check-in, the driver's
 * language rather than the staff member's.
 */
final readonly class EmailCheckInPdfAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @throws ValidationException when the check-in has no driver email.
     * @throws Throwable
     */
    public function handle(CheckIn $checkIn, User $staff): void
    {
        $recipient = $checkIn->drivers_email;

        if ($recipient === null || $recipient === '') {
            throw ValidationException::withMessages([
                'email' => 'This driver has no email on file.',
            ]);
        }

        DB::transaction(function () use ($checkIn, $staff, $recipient): void {
            Mail::to($recipient)->queue(
                new RecordDocumentMail($checkIn)
                    ->onQueue('pdf')
                    ->locale($checkIn->locale)
                    ->afterCommit(),
            );

            $this->recordHistory->handle($checkIn, RecordHistoryEvent::PdfEmailed, channel: 'mail', user: $staff, context: [
                'recipient' => $recipient,
                'status' => $checkIn->status->value,
            ]);

            $this->recordUserHistory->handle($staff, UserHistoryEvent::CheckInPdfEmailed, [
                'check_in' => $checkIn->uuid,
                'recipient' => $recipient,
                'status' => $checkIn->status->value,
            ]);
        });
    }
}
