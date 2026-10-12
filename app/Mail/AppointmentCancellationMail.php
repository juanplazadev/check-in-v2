<?php

declare(strict_types=1);

namespace App\Mail;

use App\Account\HistoryRecordResolver;
use App\Mail\Concerns\FiltersBlankRows;
use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The driver's confirmation that their booking was cancelled.
 *
 * Distinct from {@see AppointmentCancelled}, which tells the shipping
 * department the slot is free. This one is addressed to the driver and is
 * translated. No PDF: the confirmation document describes a booking that no
 * longer stands, so attaching it would only invite someone to show it at a gate.
 */
final class AppointmentCancellationMail extends Mailable implements ShouldQueue
{
    use FiltersBlankRows;
    use Queueable;
    use SerializesModels;

    public function __construct(public Appointment $appointment) {}

    public function envelope(): Envelope
    {
        $this->appointment->loadMissing('location');

        return new Envelope(
            subject: __('messages.appointmentCancellationEmail.subject', [
                'reference' => $this->appointment->reference_number,
                'location' => $this->appointment->location->name,
            ]),
        );
    }

    public function content(): Content
    {
        $this->appointment->loadMissing(['location.address', 'purchaseOrders']);

        $record = resolve(HistoryRecordResolver::class)->appointment($this->appointment);

        return new Content(
            view: 'mail.notification',
            with: [
                'preheader' => __('messages.appointmentCancellationEmail.preheader', [
                    'location' => $record['locationName'],
                ]),
                'eyebrow' => __('messages.appointmentCancellationEmail.eyebrow'),
                'title' => __('messages.appointmentCancellationEmail.title'),
                'intro' => __('messages.appointmentCancellationEmail.intro'),
                'referenceLabel' => __('messages.accountHistoryRecord.reference'),
                'referenceNumber' => $record['referenceNumber'],
                'rows' => $this->presentRows([
                    ['label' => __('messages.accountHistoryRecord.locationHeading'), 'value' => $record['locationName'], 'sub' => $record['locationAddress']],
                    ['label' => __('messages.accountHistoryRecord.date'), 'value' => $record['date']],
                    ['label' => __('messages.accountHistoryRecord.time'), 'value' => $record['time']],
                    ['label' => __('messages.accountHistoryRecord.purchaseOrders'), 'value' => implode(', ', $record['purchaseOrders'])],
                    ['label' => __('messages.accountHistoryRecord.cancellationReason'), 'value' => $record['cancelledReason']],
                ]),
            ],
        );
    }
}
