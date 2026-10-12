<?php

declare(strict_types=1);

use App\Actions\RecordHistoryAction;
use App\Enums\RecordHistoryEvent;
use App\Models\CheckIn;
use App\Models\User;

function recorder(): RecordHistoryAction
{
    return resolve(RecordHistoryAction::class);
}

it('redacts secrets while keeping the fact they were involved', function (string $key): void {
    $checkIn = CheckIn::factory()->create();

    $row = recorder()->handle(
        record: $checkIn,
        event: RecordHistoryEvent::Created,
        context: [$key => 'the-real-value', 'harmless' => 'kept'],
    );

    expect($row->context[$key])->toBe(['redacted' => true])
        ->and($row->context['harmless'])->toBe('kept');
})->with([
    'licence' => ['drivers_license_number'],
    'password' => ['password'],
    'two factor secret' => ['two_factor_secret'],
]);

/*
 * Matches RecordUserHistoryAction: an empty context is absence, not an empty
 * object, so a reader can test for null rather than for {}.
 */
it('stores an empty context as null', function (): void {
    $row = recorder()->handle(
        record: CheckIn::factory()->create(),
        event: RecordHistoryEvent::Created,
    );

    expect($row->context)->toBeNull();
});

it('falls back to the record locale and driver when none are given', function (): void {
    $driver = User::query()->create([
        'name' => 'John Driver',
        'email' => 'recorder@example.com',
        'password' => 'secret-password',
    ]);

    $checkIn = CheckIn::factory()->forUser($driver)->create(['locale' => 'es']);

    $row = recorder()->handle(record: $checkIn, event: RecordHistoryEvent::Created);

    expect($row->locale)->toBe('es')
        ->and($row->user_id)->toBe($driver->id);
});

it('prefers an explicitly passed locale over the record one', function (): void {
    $checkIn = CheckIn::factory()->create(['locale' => 'es']);

    $row = recorder()->handle(
        record: $checkIn,
        event: RecordHistoryEvent::NotificationSent,
        locale: 'fr',
    );

    expect($row->locale)->toBe('fr');
});
