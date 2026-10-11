<?php

declare(strict_types=1);

use App\Actions\CompleteCheckInAction;
use App\CheckIn\CheckInScheduleResolver;
use App\Dashboard\RecordHistoryEntryResolver;
use App\Enums\CheckInErpStatus;
use App\Enums\NotificationChannel;
use App\Enums\RecordHistoryEvent;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\RecordHistory;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\CheckInCopy;
use App\Sms\SmsMessage;
use App\Sms\SmsSender;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

/**
 * @param  array<string, mixed>  $attributes
 */
function trailDriver(array $attributes = []): User
{
    return User::query()->create([
        'name' => 'John Driver',
        'email' => 'trail-driver@example.com',
        'password' => 'secret-password',
        ...$attributes,
    ]);
}

/**
 * @param  array<string, mixed>  $config
 */
function completeCheckInFor(?User $user, array $config = ['checkin' => ['email_addresses' => ['shipping@example.com']]]): CheckIn
{
    if ($user instanceof User) {
        test()->actingAs($user);
    }

    $location = Location::factory()->create(['config' => $config]);
    $dto = resolve(CheckInScheduleResolver::class)->buildDTO($location, now());

    return resolve(CompleteCheckInAction::class)->handle([
        'customer' => 'Acme Produce',
        'destination_city' => 'Phoenix',
        'destination_state' => 'Arizona',
        'destination_country' => 'US',
        'po_numbers' => ['SO-00001'],
        'truck_name' => 'Truck 42',
        'truck_plate' => 'ABC1234',
        'trailer_plate' => 'XYZ9876',
        'trailer_chute' => 'center-chute',
        'drivers_name' => 'John Doe',
        'drivers_cellphone' => '+12015550123',
        'drivers_license_number' => 'D12345678',
    ], $dto, $user);
}

/**
 * @return list<string>
 */
function trailEvents(CheckIn $checkIn): array
{
    return $checkIn->history()->get()->map(fn ($row): string => $row->event->value)->all();
}

beforeEach(function (): void {
    Date::setTestNow(Date::today('UTC')->setTime(8, 0));
});

/*
 * The whole point of the feature: an admin opens a check-in and reads what
 * happened, in order.
 */
it('reads back as a timeline in the order things happened', function (): void {
    $checkIn = completeCheckInFor(trailDriver());

    expect(trailEvents($checkIn))->toBe([
        RecordHistoryEvent::Created->value,
        RecordHistoryEvent::EmployeeNotificationQueued->value,
        RecordHistoryEvent::NotificationSent->value,
    ]);
});

it('does not log a status change for the status a record is created with', function (): void {
    $checkIn = completeCheckInFor(trailDriver());

    expect(trailEvents($checkIn))->not->toContain(RecordHistoryEvent::StatusChanged->value);
});

it('records one row per channel, each carrying the record locale', function (): void {
    $driver = trailDriver([
        'cellphone' => '+12015550123',
        'notification_channel' => NotificationChannel::Both,
    ]);

    $checkIn = completeCheckInFor($driver);

    $sent = $checkIn->history()
        ->where('event', RecordHistoryEvent::NotificationSent)
        ->get();

    expect($sent)->toHaveCount(2)
        ->and($sent->pluck('channel')->all())->toEqualCanonicalizing(['mail', 'sms'])
        ->and($sent->pluck('subject')->unique()->all())->toBe(['check-in-copy'])
        ->and($sent->pluck('locale')->unique()->all())->toBe([$checkIn->locale])
        ->and($sent->pluck('user_id')->unique()->all())->toBe([$driver->id]);
});

it('records the employee notification with the addresses it went to', function (): void {
    $checkIn = completeCheckInFor(trailDriver(), [
        'checkin' => ['email_addresses' => ['shipping@example.com', 'ops@example.com']],
    ]);

    $row = $checkIn->history()
        ->where('event', RecordHistoryEvent::EmployeeNotificationQueued)
        ->sole();

    expect($row->channel)->toBe('mail')
        ->and($row->subject)->toBe('check-in-completed')
        ->and($row->context['recipients'])->toBe(['shipping@example.com', 'ops@example.com']);
});

/*
 * A guest has no account, but they do have a validated cellphone, so the trail
 * shows the record arriving, the yard being told, and the driver being texted.
 */
it('records the text a guest check-in sends the driver', function (): void {
    $checkIn = completeCheckInFor(null);

    expect(trailEvents($checkIn))->toBe([
        RecordHistoryEvent::Created->value,
        RecordHistoryEvent::EmployeeNotificationQueued->value,
        RecordHistoryEvent::NotificationSent->value,
    ]);
});

it('leaves the user column null on a guest notification row', function (): void {
    $checkIn = completeCheckInFor(null);

    $row = $checkIn->history()
        ->where('event', RecordHistoryEvent::NotificationSent)
        ->sole();

    expect($row->channel)->toBe('sms')
        ->and($row->subject)->toBe('check-in-copy')
        ->and($row->user_id)->toBeNull();
});

it('marks a guest check-in as such on the created row', function (): void {
    $guest = completeCheckInFor(null);
    $signedIn = completeCheckInFor(trailDriver());

    expect($guest->history()->first()->context['guest'])->toBeTrue()
        ->and($signedIn->history()->first()->context['guest'])->toBeFalse();
});

it('logs a status transition with what it moved between', function (): void {
    $checkIn = completeCheckInFor(trailDriver());

    $checkIn->forceFill(['erp_status' => CheckInErpStatus::Synced])->save();

    $row = $checkIn->history()
        ->where('event', RecordHistoryEvent::StatusChanged)
        ->sole();

    expect($row->subject)->toBe('erp_status')
        ->and($row->context['from'])->toBe(CheckInErpStatus::Pending->value)
        ->and($row->context['to'])->toBe(CheckInErpStatus::Synced->value);
});

it('stores the morph alias rather than the class name', function (): void {
    $checkIn = completeCheckInFor(trailDriver());

    expect($checkIn->history()->first()->recordable_type)->toBe('check_in');
});

/*
 * The trail is read by staff, and the licence is encrypted at rest everywhere
 * else, so it must not be sitting in plain text in a context blob.
 */
it('never lets the licence number into the trail', function (): void {
    $checkIn = completeCheckInFor(trailDriver());

    $contexts = $checkIn->history()->get()->map(fn ($row): string => json_encode($row->context) ?: '');

    expect($contexts->implode(' '))->not->toContain('D12345678');
});

/*
 * The channel throws, Laravel dispatches NotificationFailed and rethrows. What
 * matters for support is that the attempt is on the record with a reason, not
 * that it silently vanished.
 */
it('records a failed send with its reason and no sent row', function (): void {
    $this->swap(SmsSender::class, new class implements SmsSender
    {
        public function send(SmsMessage $message): void
        {
            throw new RuntimeException('carrier rejected the number');
        }
    });

    $driver = trailDriver([
        'cellphone' => '+12015550123',
        'notification_channel' => NotificationChannel::Sms,
    ]);

    $checkIn = CheckIn::factory()->forUser($driver)->create();

    try {
        $driver->notify(new CheckInCopy($checkIn));
    } catch (RuntimeException) {
        // Rethrown by the framework after the failure event; not the subject here.
    }

    $row = $checkIn->history()
        ->where('event', RecordHistoryEvent::NotificationFailed)
        ->sole();

    expect($row->channel)->toBe('sms')
        ->and($row->subject)->toBe('check-in-copy')
        ->and($row->context['reason'])->toBe('carrier rejected the number')
        ->and($checkIn->history()->where('event', RecordHistoryEvent::NotificationSent)->count())->toBe(0);
});

/*
 * A registered driver's text goes to their profile cellphone, which need not be
 * the number typed on the check-in. Staff helping a driver need to see which.
 */
it('records where each copy was addressed for a registered driver', function (): void {
    $driver = trailDriver([
        'cellphone' => '+12015550199',
        'notification_channel' => NotificationChannel::Both,
    ]);

    $checkIn = completeCheckInFor($driver);

    $recipients = $checkIn->history()
        ->where('event', RecordHistoryEvent::NotificationSent)
        ->get()
        ->mapWithKeys(fn (RecordHistory $row): array => [$row->channel => $row->context['recipient']]);

    expect($checkIn->drivers_cellphone)->toBe('+12015550123')
        ->and($recipients->all())->toEqualCanonicalizing([
            'sms' => '+12015550199',
            'mail' => 'trail-driver@example.com',
        ]);
});

it('records the check-in number as the recipient of a guest text', function (): void {
    $checkIn = completeCheckInFor(null);

    $row = $checkIn->history()
        ->where('event', RecordHistoryEvent::NotificationSent)
        ->sole();

    expect($row->context['recipient'])->toBe('+12015550123');
});

/*
 * The cellphone is optional on a profile, and a queued job can outlive it. The
 * trail must say the text was skipped, not that it was sent.
 */
it('records a skipped text when the driver no longer has a cellphone', function (): void {
    $driver = trailDriver(['cellphone' => null]);
    $checkIn = CheckIn::factory()->forUser($driver)->create();

    Notification::sendNow($driver, new CheckInCopy($checkIn), [SmsChannel::class]);

    $row = $checkIn->history()
        ->where('event', RecordHistoryEvent::NotificationSkipped)
        ->sole();

    expect($row->channel)->toBe('sms')
        ->and($row->subject)->toBe('check-in-copy')
        ->and($row->context['reason'])->toBe('no-recipient')
        ->and($checkIn->history()->where('event', RecordHistoryEvent::NotificationSent)->count())->toBe(0)
        ->and($checkIn->history()->where('event', RecordHistoryEvent::NotificationFailed)->count())->toBe(0);
});

it('describes where a copy went for staff', function (): void {
    $driver = trailDriver([
        'cellphone' => '+12015550199',
        'notification_channel' => NotificationChannel::Both,
    ]);

    $checkIn = completeCheckInFor($driver);
    $resolver = resolve(RecordHistoryEntryResolver::class);

    $descriptions = $checkIn->history()
        ->where('event', RecordHistoryEvent::NotificationSent)
        ->get()
        ->map(fn (RecordHistory $row): string => $resolver->entry($row, 'Check-in submitted')['description']);

    expect($descriptions->all())->toEqualCanonicalizing([
        'Check-in copy sent by SMS to +1 (201) 555-0199',
        'Check-in copy sent by email to trail-driver@example.com',
    ]);
});

it('describes a row written before recipients were captured by its channel alone', function (): void {
    $row = RecordHistory::factory()->create([
        'event' => RecordHistoryEvent::NotificationSent,
        'subject' => 'check-in-copy',
        'channel' => 'sms',
    ]);

    expect(resolve(RecordHistoryEntryResolver::class)->entry($row, 'Check-in submitted')['description'])
        ->toBe('Check-in copy sent by SMS');
});
