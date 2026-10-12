<?php

declare(strict_types=1);

use App\Notifications\Channels\SmsChannel;
use App\Sms\SmsMessage;
use App\Sms\SmsSender;
use Illuminate\Notifications\Notification;

/**
 * Records what it was handed instead of sending it, so the channel can be
 * tested without a carrier.
 */
final class RecordingSmsSender implements SmsSender
{
    /** @var list<SmsMessage> */
    public array $sent = [];

    public function send(SmsMessage $message): void
    {
        $this->sent[] = $message;
    }
}

final class TextableNotification extends Notification
{
    public function toSms(mixed $notifiable): SmsMessage
    {
        return new SmsMessage(to: '+12015550123', body: 'Ref ABCD2345');
    }
}

/** A notification with no SMS payload, which must never be routed to the channel. */
final class MailOnlyNotification extends Notification {}

function notifiableWith(?string $number): object
{
    return new readonly class($number)
    {
        public function __construct(private ?string $number) {}

        public function routeNotificationFor(string $driver, mixed $notification = null): ?string
        {
            return $this->number;
        }
    };
}

it('hands the message to the sender', function (): void {
    $sender = new RecordingSmsSender;

    new SmsChannel($sender)->send(notifiableWith('+12015550123'), new TextableNotification);

    expect($sender->sent)->toHaveCount(1)
        ->and($sender->sent[0]->to)->toBe('+12015550123')
        ->and($sender->sent[0]->body)->toBe('Ref ABCD2345');
});

/*
 * SkipUnroutableSms cancels a text with no number before it gets here. A quiet
 * return used to let Laravel log the text as sent, so reaching this is a bug.
 */
it('throws when the notifiable has no number', function (): void {
    $sender = new RecordingSmsSender;

    expect(fn () => new SmsChannel($sender)->send(notifiableWith(null), new TextableNotification))
        ->toThrow(LogicException::class);

    expect($sender->sent)->toBeEmpty();
});

it('throws when the notification has no text payload', function (): void {
    $sender = new RecordingSmsSender;

    expect(fn () => new SmsChannel($sender)->send(notifiableWith('+12015550123'), new MailOnlyNotification))
        ->toThrow(LogicException::class);

    expect($sender->sent)->toBeEmpty();
});
