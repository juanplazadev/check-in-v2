<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Sms\SmsMessage;
use App\Sms\SmsSender;
use Illuminate\Notifications\Notification;
use LogicException;

/**
 * Delivers a notification's toSms() payload through whichever
 * {@see SmsSender} is bound.
 *
 * Named as a channel class rather than a string alias so `via()` can reference
 * it without a service-provider registration.
 *
 * Every guard here throws rather than returning quietly. A quiet return still
 * lets Laravel fire NotificationSent, which put "sent by SMS" in the trail for
 * a text that never went out. A missing number is caught earlier by
 * {@see \App\Listeners\SkipUnroutableSms}, and the other two are programmer
 * errors, so reaching any of them is a bug that should surface as a failure.
 */
final readonly class SmsChannel
{
    public function __construct(private SmsSender $sender) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        $number = $notifiable->routeNotificationFor('sms', $notification);

        if (! is_string($number) || $number === '') {
            throw new LogicException(sprintf('%s reached the SMS channel with no number to send to.', $notification::class));
        }

        if (! method_exists($notification, 'toSms')) {
            throw new LogicException(sprintf('%s is sent by SMS but has no toSms() method.', $notification::class));
        }

        $message = $notification->toSms($notifiable);

        if (! $message instanceof SmsMessage) {
            throw new LogicException(sprintf('%s::toSms() must return an %s.', $notification::class, SmsMessage::class));
        }

        $this->sender->send($message);
    }
}
