<?php

declare(strict_types=1);

namespace App\Mail;

use App\Account\HistoryRecordResolver;
use App\Mail\Concerns\FiltersBlankRows;
use App\Models\Appointment;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the driver their booking has a new time, with the old one for
 * reference. Addressed to the driver and translated.
 */
final class AppointmentRescheduledMail extends Mailable implements ShouldQueue
{
    use FiltersBlankRows;
    use Queueable;
    use SerializesModels;

    public function __construct(public Appointment $appointment, public CarbonInterface $previousStart) {}

    public function envelope(): Envelope
    {
        $this->appointment->loadMissing('location');

        return new Envelope(
            subject: __('messages.appointmentRescheduleEmail.subject', [
                'reference' => $this->appointment->reference_number,
                'location' => $this->appointment->location->name,
            ]),
        );
    }

    public function content(): Content
    {
        $this->appointment->loadMissing(['location.address', 'purchaseOrders', 'loadType']);

        $record = resolve(HistoryRecordResolver::class)->appointment($this->appointment);
        $previous = $this->previousStart->copy()->setTimezone($this->appointment->location->timezone);

        return new Content(
            view: 'mail.notification',
            with: [
                'preheader' => __('messages.appointmentRescheduleEmail.preheader', [
                    'location' => $record['locationName'],
                ]),
                'eyebrow' => __('messages.appointmentRescheduleEmail.eyebrow'),
                'title' => __('messages.appointmentRescheduleEmail.title'),
                'intro' => __('messages.appointmentRescheduleEmail.intro'),
                'referenceLabel' => __('messages.accountHistoryRecord.reference'),
                'referenceNumber' => $record['referenceNumber'],
                'rows' => $this->presentRows([
                    ['label' => __('messages.accountHistoryRecord.locationHeading'), 'value' => $record['locationName'], 'sub' => $record['locationAddress']],
                    ['label' => __('messages.accountHistoryRecord.date'), 'value' => $record['date']],
                    ['label' => __('messages.accountHistoryRecord.time'), 'value' => $record['timeRange']],
                    ['label' => __('messages.appointmentRescheduleEmail.previousTime'), 'value' => sprintf('%s, %s', $previous->translatedFormat('M j, Y'), $previous->format('g:i A T'))],
                    ['label' => __('messages.accountHistoryRecord.loadType'), 'value' => $record['loadType']],
                    ['label' => __('messages.accountHistoryRecord.purchaseOrders'), 'value' => implode(', ', $record['purchaseOrders'])],
                ]),
            ],
        );
    }
}
