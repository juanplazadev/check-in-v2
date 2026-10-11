<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\RecordHistoryAction;
use App\Enums\RecordHistoryEvent;
use App\History\ChannelName;
use App\History\RecordNotification;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Mail\SentMessage;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Events\NotificationSkipped;
use Illuminate\Notifications\Notification;
use Throwable;

/**
 * Writes one trail row per notification per channel.
 *
 * One row per channel is the point: a driver on Both produces a mail row and an
 * sms row, which is exactly the question an admin is asking when they open the
 * record. Anything implementing {@see RecordNotification} is picked up here, so
 * new message types need no change to this class.
 */
final readonly class RecordNotificationHistory
{
    public function __construct(
        private RecordHistoryAction $history,
        private ChannelName $channels,
    ) {}

    public function handleSent(NotificationSent $event): void
    {
        if (! $event->notification instanceof RecordNotification) {
            return;
        }

        $this->record($event->notification, $event->channel, RecordHistoryEvent::NotificationSent, [
            'recipient' => $this->recipient($event->notifiable, $event->notification, $event->channel),
            'messageId' => $this->messageId($event->response),
        ]);
    }

    public function handleFailed(NotificationFailed $event): void
    {
        if (! $event->notification instanceof RecordNotification) {
            return;
        }

        $exception = $event->data['exception'] ?? null;

        $this->record($event->notification, $event->channel, RecordHistoryEvent::NotificationFailed, [
            'recipient' => $this->recipient($event->notifiable, $event->notification, $event->channel),
            'reason' => $exception instanceof Throwable ? $exception->getMessage() : null,
        ]);
    }

    /**
     * Only a text skipped by {@see SkipUnroutableSms} belongs in the trail. A
     * skip made on purpose elsewhere, through a notification's shouldSend(), is
     * a decision rather than something support needs to explain.
     */
    public function handleSkipped(NotificationSkipped $event): void
    {
        if (! $event->notification instanceof RecordNotification || $event->channel !== SmsChannel::class) {
            return;
        }

        $this->record($event->notification, $event->channel, RecordHistoryEvent::NotificationSkipped, [
            'reason' => 'no-recipient',
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function record(
        RecordNotification $notification,
        string $channel,
        RecordHistoryEvent $event,
        array $context,
    ): void {
        $record = $notification->historyRecord();
        $record->loadMissing('user');

        $user = $record->user;

        $this->history->handle(
            record: $record,
            event: $event,
            subject: $notification->historySubject(),
            channel: $this->channels->forChannel($channel),
            // The locale the notification was pinned to when it was queued, which
            // is what the driver actually received, not whatever the worker has
            // set at the moment this listener runs.
            locale: $notification->locale ?? $record->locale,
            user: $user instanceof User ? $user : null,
            context: array_filter($context, static fn (mixed $value): bool => $value !== null),
        );
    }

    /**
     * Where the message was addressed, captured now because the driver can
     * change their email or cellphone later. A registered driver's text goes to
     * the cellphone on their profile, which need not match the number typed on
     * the check-in, and that mismatch is exactly what staff need to see.
     */
    private function recipient(mixed $notifiable, Notification $notification, string $channel): ?string
    {
        if (! is_object($notifiable) || ! method_exists($notifiable, 'routeNotificationFor')) {
            return null;
        }

        $route = $notifiable->routeNotificationFor($this->channels->forChannel($channel), $notification);

        return is_string($route) && $route !== '' ? $route : null;
    }

    /**
     * Worth keeping: it is what support needs to trace a message with the ESP
     * when a driver says the email never arrived.
     */
    private function messageId(mixed $response): ?string
    {
        return $response instanceof SentMessage ? $response->getMessageId() : null;
    }
}
