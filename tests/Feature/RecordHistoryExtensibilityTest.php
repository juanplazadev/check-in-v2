<?php

declare(strict_types=1);

use App\Enums\NotificationChannel;
use App\Enums\RecordHistoryEvent;
use App\History\RecordNotification;
use App\Models\CheckIn;
use App\Models\User;
use App\Sms\SmsMessage;
use App\Sms\SmsSender;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Stands in for the messages that do not exist yet - the ERP "your check-in has
 * been processed" notice and whatever follows it. It implements
 * RecordNotification and nothing else: no enum case, no migration, no change to
 * the listener.
 *
 * If this test starts failing, someone has coupled the recorder to the two
 * notifications that happened to exist when it was written.
 */
final class ErpProcessedNotification extends Notification implements RecordNotification
{
    public function __construct(private readonly CheckIn $checkIn) {}

    public function historyRecord(): CheckIn
    {
        return $this->checkIn;
    }

    public function historySubject(): string
    {
        return 'erp-processed';
    }

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return $notifiable->notificationChannels();
    }

    public function toMail(User $notifiable): MailMessage
    {
        return new MailMessage()->line('Your check-in has been processed.');
    }

    public function toSms(User $notifiable): SmsMessage
    {
        return new SmsMessage(to: (string) $notifiable->cellphone, body: 'Processed.');
    }
}

/** Keeps the channel from reaching a real carrier during the test. */
final class SilentSmsSender implements SmsSender
{
    public function send(SmsMessage $message): void {}
}

function driverOnBothChannels(): User
{
    return User::query()->create([
        'name' => 'John Driver',
        'email' => 'future-driver@example.com',
        'password' => 'secret-password',
        'cellphone' => '+12015550123',
        'notification_channel' => NotificationChannel::Both,
    ]);
}

beforeEach(function (): void {
    $this->swap(SmsSender::class, new SilentSmsSender);
});

it('records a notification type it has never seen before', function (): void {
    $driver = driverOnBothChannels();
    $checkIn = CheckIn::factory()->forUser($driver)->create();

    $driver->notify(new ErpProcessedNotification($checkIn));

    $rows = $checkIn->history()
        ->where('event', RecordHistoryEvent::NotificationSent)
        ->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('subject')->unique()->all())->toBe(['erp-processed'])
        ->and($rows->pluck('channel')->all())->toEqualCanonicalizing(['mail', 'sms'])
        ->and($rows->pluck('user_id')->unique()->all())->toBe([$driver->id]);
});

it('records the language a future notification went out in', function (): void {
    $driver = driverOnBothChannels();
    $checkIn = CheckIn::factory()->forUser($driver)->create(['locale' => 'fr']);

    $driver->notify(new ErpProcessedNotification($checkIn)->locale('fr'));

    expect($checkIn->history()->first()->locale)->toBe('fr');
});

/*
 * The trail is for messages tied to a record. A notification with no record -
 * a password reset, say - has no timeline to join and must not create one.
 */
it('ignores notifications that are not about a record', function (): void {
    $driver = driverOnBothChannels();
    $checkIn = CheckIn::factory()->forUser($driver)->create();

    $driver->notify(new class extends Notification
    {
        /** @return list<string> */
        public function via(User $notifiable): array
        {
            return ['mail'];
        }

        public function toMail(User $notifiable): MailMessage
        {
            return new MailMessage()->line('Unrelated.');
        }
    });

    expect($checkIn->history()->count())->toBe(0);
});
