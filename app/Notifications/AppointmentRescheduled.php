<?php

declare(strict_types=1);

namespace App\Notifications;

use App\History\RecordNotification;
use App\Mail\AppointmentRescheduledMail;
use App\Models\Appointment;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Sms\SmsMessage;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the driver staff moved their booking to a new time. See
 * {@see CheckInCopy} for why the guest text carries no link.
 */
final class AppointmentRescheduled extends Notification implements RecordNotification, ShouldQueue
{
    use SerializesModels;

    public function __construct(public Appointment $appointment, public CarbonInterface $previousStart) {}

    public function historyRecord(): Appointment
    {
        return $this->appointment;
    }

    public function historySubject(): string
    {
        return 'appointment-rescheduled';
    }

    /**
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
            'mail' => 'default',
            SmsChannel::class => 'default',
        ];
    }

    public function toMail(User $notifiable): Mailable
    {
        return new AppointmentRescheduledMail($this->appointment, $this->previousStart)->to($notifiable->email);
    }

    public function toSms(User|AnonymousNotifiable $notifiable): SmsMessage
    {
        $when = $this->appointment->scheduled_for->setTimezone($this->appointment->location->timezone);

        $replace = [
            'app' => (string) config('app.name'),
            'reference' => $this->appointment->reference_number,
            'date' => $when->translatedFormat('M j'),
            'time' => $when->format('g:i A T'),
        ];

        return new SmsMessage(
            to: (string) $notifiable->routeNotificationFor('sms'),
            body: $notifiable instanceof User
                ? __('messages.appointmentRescheduleSms.body', [...$replace, 'url' => $this->appointment->shortUrl()])
                : __('messages.appointmentRescheduleSms.guestBody', $replace),
        );
    }
}
