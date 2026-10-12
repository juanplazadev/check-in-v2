<?php

declare(strict_types=1);

namespace App\Notifications;

use App\History\RecordNotification;
use App\Mail\AppointmentCopyMail;
use App\Models\Appointment;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Sms\SmsMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * The driver's own confirmation of a booking. See {@see CheckInCopy} for why
 * this is a notification, why the text carries no signed URL, and why the guest
 * path is texts only with no link at all.
 */
final class AppointmentCopy extends Notification implements RecordNotification, ShouldQueue
{
    use SerializesModels;

    public function __construct(public Appointment $appointment) {}

    public function historyRecord(): Appointment
    {
        return $this->appointment;
    }

    public function historySubject(): string
    {
        return 'appointment-copy';
    }

    /**
     * A guest is reachable only by text, so there is no preference to consult.
     *
     * @return list<string>
     */
    public function via(User|AnonymousNotifiable $notifiable): array
    {
        return $notifiable instanceof User
            ? $notifiable->notificationChannels()
            : [SmsChannel::class];
    }

    /**
     * @return array<string, string>
     */
    public function viaQueues(): array
    {
        return [
            'mail' => 'pdf',
            SmsChannel::class => 'default',
        ];
    }

    public function toMail(User $notifiable): Mailable
    {
        return new AppointmentCopyMail($this->appointment)->to($notifiable->email);
    }

    /** Auth-gated, shortened, and link-free for guests, all for the reasons in {@see CheckInCopy::toSms()}. */
    public function toSms(User|AnonymousNotifiable $notifiable): SmsMessage
    {
        $to = (string) $notifiable->routeNotificationFor('sms');

        if (! $notifiable instanceof User) {
            return new SmsMessage(
                to: $to,
                body: __('messages.appointmentCopySms.guestBody', [
                    'app' => (string) config('app.name'),
                    'reference' => $this->appointment->reference_number,
                ]),
            );
        }

        return new SmsMessage(
            to: $to,
            body: __('messages.appointmentCopySms.body', [
                'app' => (string) config('app.name'),
                'reference' => $this->appointment->reference_number,
                'url' => $this->appointment->shortUrl(),
            ]),
        );
    }
}
