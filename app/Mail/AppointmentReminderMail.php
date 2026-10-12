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
 * The driver's day-before reminder.
 *
 * No PDF, unlike {@see AppointmentCopyMail}: the driver was sent the
 * confirmation document when they booked, and re-rendering it here would put
 * every reminder on the pdf queue for a message whose whole job is to be short.
 */
final class AppointmentReminderMail extends Mailable implements ShouldQueue
{
    use FiltersBlankRows;
    use Queueable;
    use SerializesModels;

    public function __construct(public Appointment $appointment) {}

    public function envelope(): Envelope
    {
        $this->appointment->loadMissing('location');

        return new Envelope(
            subject: __('messages.appointmentReminderEmail.subject', [
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
                'preheader' => __('messages.appointmentReminderEmail.preheader', [
                    'location' => $record['locationName'],
                ]),
                'eyebrow' => __('messages.appointmentReminderEmail.eyebrow'),
                'title' => __('messages.appointmentReminderEmail.title'),
                'intro' => __('messages.appointmentReminderEmail.intro'),
                'referenceLabel' => __('messages.accountHistoryRecord.reference'),
                'referenceNumber' => $record['referenceNumber'],
                'rows' => $this->presentRows([
                    ['label' => __('messages.accountHistoryRecord.locationHeading'), 'value' => $record['locationName'], 'sub' => $record['locationAddress']],
                    ['label' => __('messages.accountHistoryRecord.date'), 'value' => $record['date']],
                    ['label' => __('messages.accountHistoryRecord.time'), 'value' => $record['time']],
                    ['label' => __('messages.accountHistoryRecord.purchaseOrders'), 'value' => implode(', ', $record['purchaseOrders'])],
                    ['label' => __('messages.accountHistoryRecord.driverName'), 'value' => $record['driversName']],
                ]),
            ],
        );
    }
}
