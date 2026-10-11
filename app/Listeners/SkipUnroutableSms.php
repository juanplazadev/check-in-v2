<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Notifications\Channels\SmsChannel;
use Illuminate\Notifications\Events\NotificationSending;

/**
 * Cancels a text that has no number to go to, before the channel is reached.
 *
 * A registered driver's text goes to the cellphone on their profile, which is
 * optional. User::notificationChannels() drops SMS when it is empty, but only at
 * dispatch: a driver who clears their number while the notification sits in the
 * queue would otherwise reach the channel with nowhere to send it. Returning
 * false makes Laravel fire NotificationSkipped instead of NotificationSent, so
 * the trail records the skip rather than a text that never went out.
 *
 * A guest's number comes from the check-in form, which requires it, so a guest
 * never lands here.
 */
final readonly class SkipUnroutableSms
{
    public function handle(NotificationSending $event): ?bool
    {
        if ($event->channel !== SmsChannel::class) {
            return null;
        }

        $number = $event->notifiable->routeNotificationFor('sms', $event->notification);

        return is_string($number) && $number !== '' ? null : false;
    }
}
