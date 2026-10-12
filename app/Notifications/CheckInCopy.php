<?php

declare(strict_types=1);

namespace App\Notifications;

use App\History\RecordNotification;
use App\Mail\CheckInCopyMail;
use App\Models\CheckIn;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Sms\SmsMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * The driver's own confirmation of a check-in.
 *
 * A notification rather than a plain mailable because the driver chooses the
 * channel. The two channels carry deliberately different payloads: a text
 * cannot hold a PDF, so it carries the reference and a link to the record
 * instead.
 *
 * Serves both a registered driver and a guest. A guest has no account, so no
 * preference to read and no email on file - every check-in form collects a
 * validated cellphone and nothing else - which is why the guest path is texts
 * only. See {@see \App\Actions\SendCheckInCopyAction} for the routing.
 */
final class CheckInCopy extends Notification implements RecordNotification, ShouldQueue
{
    use SerializesModels;

    public function __construct(public CheckIn $checkIn) {}

    public function historyRecord(): CheckIn
    {
        return $this->checkIn;
    }

    public function historySubject(): string
    {
        return 'check-in-copy';
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
     * Only the mail path renders a PDF, so a text-only driver never occupies
     * the dedicated Browsershot supervisor.
     *
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
        return new CheckInCopyMail($this->checkIn)->to($notifiable->email);
    }

    /**
     * A registered driver's text carries a link; a guest's deliberately does not.
     *
     * The link points at the account history detail page, which
     * {@see \App\Account\HistoryRecordFinder} already scopes to the owning
     * driver. That is why no signed URL is involved: a signed URL would open
     * the record for anyone the text was forwarded to, where this 404s. It goes
     * out shortened, via {@see \App\ShortLink\ShortLinkUrlGenerator}, because
     * the account history path plus a uuid ran to about 87 characters of a
     * 160-character segment.
     *
     * That target is exactly why a guest gets no link: it is auth-gated, so a
     * guest tapping it lands on the login page with no account to sign in to.
     * The public confirmation page is no better - it is restricted to records
     * still Pending, so it would 404 as soon as the dock processed the record.
     * The reference number is the part a driver quotes at the gate anyway, and
     * a link that dies within hours costs more trust than it buys.
     */
    public function toSms(User|AnonymousNotifiable $notifiable): SmsMessage
    {
        // routeNotificationFor('sms') covers both: User::routeNotificationForSms()
        // returns the stored cellphone, and an AnonymousNotifiable returns
        // whatever number the action routed it to.
        $to = (string) $notifiable->routeNotificationFor('sms');

        if (! $notifiable instanceof User) {
            return new SmsMessage(
                to: $to,
                body: __('messages.checkInCopySms.guestBody', [
                    'app' => (string) config('app.name'),
                    'reference' => $this->checkIn->reference_number,
                ]),
            );
        }

        return new SmsMessage(
            to: $to,
            body: __('messages.checkInCopySms.body', [
                'app' => (string) config('app.name'),
                'reference' => $this->checkIn->reference_number,
                'url' => $this->checkIn->shortUrl(),
            ]),
        );
    }
}
