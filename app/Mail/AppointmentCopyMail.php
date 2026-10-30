<?php

declare(strict_types=1);

namespace App\Mail;

use App\Account\HistoryRecordResolver;
use App\Mail\Concerns\AttachesRecordPdf;
use App\Mail\Concerns\FiltersBlankRows;
use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The driver's own copy of a booking, with the PDF confirmation attached.
 *
 * Distinct from {@see AppointmentBooked}, which tells the shipping department a
 * slot has been taken. This one is addressed to the driver and is translated.
 */
final class AppointmentCopyMail extends Mailable implements ShouldQueue
{
    use AttachesRecordPdf;
    use FiltersBlankRows;
    use Queueable;
    use SerializesModels;

    public function __construct(public Appointment $appointment) {}

    public function envelope(): Envelope
    {
        $this->appointment->loadMissing('location');

        return new Envelope(
            subject: __('messages.appointmentCopyEmail.subject', [
                'reference' => $this->appointment->reference_number,
                'location' => $this->appointment->location->name,
            ]),
        );
    }

    public function content(): Content
    {
        $this->appointment->loadMissing(['location.address', 'purchaseOrders', 'loadType']);

        $record = resolve(HistoryRecordResolver::class)->appointment($this->appointment);

        return new Content(
            view: 'mail.notification',
            with: [
                'preheader' => __('messages.appointmentCopyEmail.preheader', [
                    'location' => $record['locationName'],
                ]),
                'eyebrow' => __('messages.appointmentCopyEmail.eyebrow'),
                'title' => __('messages.appointmentCopyEmail.title'),
                'intro' => __('messages.appointmentCopyEmail.intro'),
                'referenceLabel' => __('messages.accountHistoryRecord.reference'),
                'referenceNumber' => $record['referenceNumber'],
                'rows' => $this->presentRows([
                    ['label' => __('messages.accountHistoryRecord.locationHeading'), 'value' => $record['locationName'], 'sub' => $record['locationAddress']],
                    ['label' => __('messages.accountHistoryRecord.date'), 'value' => $record['date']],
                    ['label' => __('messages.accountHistoryRecord.time'), 'value' => $record['timeRange']],
                    ['label' => __('messages.accountHistoryRecord.loadType'), 'value' => $record['loadType']],
                    ['label' => __('messages.accountHistoryRecord.purchaseOrders'), 'value' => implode(', ', $record['purchaseOrders'])],
                    ['label' => __('messages.accountHistoryRecord.driverName'), 'value' => $record['driversName']],
                ]),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return $this->recordPdfAttachments($this->appointment);
    }
}
