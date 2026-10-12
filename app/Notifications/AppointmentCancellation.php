<?php

declare(strict_types=1);

namespace App\Notifications;

use App\History\RecordNotification;
use App\Mail\AppointmentCancellationMail;
use App\Models\Appointment;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Sms\SmsMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * The driver's confirmation that their booking was cancelled. See
 * {@see AppointmentCopy} for why this is a notification rather than a mailable.
 */
final class AppointmentCancellation extends Notification implements RecordNotification, ShouldQueue
{
    use SerializesModels;

    public function __construct(public Appointment $appointment) {}

    public function historyRecord(): Appointment
    {
        return $this->appointment;
    }

    public function historySubject(): string
    {
        return 'appointment-cancellation';
    }

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return $notifiable->notificationChannels();
    }

    /**
     * No PDF is attached, so this has no business on the "pdf" queue the way
     * {@see AppointmentCopy} does.
     *
     * @return array<string, string>
     */
    public function viaQueues(): array
    {
        return [
            'mail' => 'default',
            SmsChannel::class => 'default',
        ];
    }

    public function toMail(User $notifiable): Mailable
    {
        return new AppointmentCancellationMail($this->appointment)->to($notifiable->email);
    }

    /** Auth-gated and shortened for the same reasons as {@see CheckInCopy::toSms()}. */
    public function toSms(User $notifiable): SmsMessage
    {
        return new SmsMessage(
            to: (string) $notifiable->cellphone,
            body: __('messages.appointmentCancellationSms.body', [
                'app' => (string) config('app.name'),
                'reference' => $this->appointment->reference_number,
                'url' => $this->appointment->shortUrl(),
            ]),
        );
    }
}
