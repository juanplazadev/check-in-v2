<?php

declare(strict_types=1);

use App\Enums\RecordHistoryEvent;
use App\Enums\Role;
use App\Enums\UserHistoryEvent;
use App\Models\AppointmentScheduleOverride;
use App\Models\CheckInScheduleOverride;
use App\Models\Location;
use App\Models\RecordHistory;
use App\Models\User;
use App\Models\UserHistory;
use Inertia\Testing\AssertableInertia;

/*
 * Adding and removing a location's closures and special hours from its closures page.
 */

function closuresManager(Location $location): User
{
    return User::factory()->withRole(Role::ShippingManager)->worksAt($location)->create();
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function closurePayload(array $overrides = []): array
{
    return [
        'date' => '2026-12-25',
        'reason' => 'Christmas',
        'is_closed' => true,
        'applies_to' => ['checkIns', 'appointments'],
        ...$overrides,
    ];
}

beforeEach(function (): void {
    // 03:00 UTC on Dec 2 is still Dec 1 in Chicago.
    $this->travelTo('2026-12-02 03:00:00');
    $this->location = Location::factory()->create(['name' => 'Alpha Dock', 'timezone' => 'America/Chicago']);
});

it('closes both features for the day and logs it once on both trails', function (): void {
    $manager = closuresManager($this->location);

    $this->actingAs($manager)
        ->from(route('dashboard.locations.closures', ['uuid' => $this->location->uuid]))
        ->post(route('dashboard.locations.closures.store', ['uuid' => $this->location->uuid]), closurePayload())
        ->assertRedirect(route('dashboard.locations.closures', ['uuid' => $this->location->uuid]))
        ->assertInertiaFlash('toast.message', 'Closure on Dec 25 added.');

    $checkIn = $this->location->checkinScheduleOverrides()->sole();
    $appointment = $this->location->appointmentScheduleOverrides()->sole();
    $history = $this->location->history()->sole();
    $trail = UserHistory::query()->sole();
    $context = ['date' => '2026-12-25', 'reason' => 'Christmas', 'hours' => 'Closed', 'appliesTo' => ['checkIns', 'appointments']];

    expect($checkIn->override_date->toDateString())->toBe('2026-12-25')
        ->and($checkIn->is_closed)->toBeTrue()
        ->and($checkIn->open_time)->toBeNull()
        ->and($appointment->reason)->toBe('Christmas')
        ->and($history->event)->toBe(RecordHistoryEvent::ClosureAdded)
        ->and($history->user_id)->toBe($manager->id)
        ->and($history->context)->toBe($context)
        ->and($trail->event)->toBe(UserHistoryEvent::LocationClosureAdded)
        ->and($trail->changes)->toBe(['location' => $this->location->uuid, ...$context]);
});

it('keeps special hours for one feature', function (): void {
    $this->actingAs(closuresManager($this->location))
        ->post(route('dashboard.locations.closures.store', ['uuid' => $this->location->uuid]), closurePayload([
            'date' => '2026-12-24',
            'reason' => 'Christmas Eve',
            'is_closed' => false,
            'open_time' => '08:00',
            'close_time' => '23:59',
            'applies_to' => ['checkIns'],
        ]))
        ->assertSessionHasNoErrors();

    $override = $this->location->checkinScheduleOverrides()->sole();

    expect($override->is_closed)->toBeFalse()
        ->and($override->open_time)->toBe('08:00:00')
        ->and($override->close_time)->toBe('23:59:59')
        ->and($this->location->appointmentScheduleOverrides()->count())->toBe(0)
        ->and($this->location->history()->sole()->context['hours'])->toBe('8:00 AM - 11:59 PM');
});

it("accepts today in the location's timezone but not the day before", function (): void {
    $manager = closuresManager($this->location);
    $url = route('dashboard.locations.closures.store', ['uuid' => $this->location->uuid]);

    // Already Dec 2 in UTC, but Dec 1 is today in Chicago.
    $this->actingAs($manager)->post($url, closurePayload(['date' => '2026-12-01']))->assertSessionHasNoErrors();

    $this->post($url, closurePayload(['date' => '2026-11-30']))
        ->assertSessionHasErrors(['date' => 'Choose today or a later date.']);
});

it('rejects invalid closures', function (array $payload, string $field): void {
    $this->actingAs(closuresManager($this->location))
        ->post(route('dashboard.locations.closures.store', ['uuid' => $this->location->uuid]), closurePayload($payload))
        ->assertSessionHasErrors($field);

    expect(CheckInScheduleOverride::query()->count())->toBe(0)
        ->and(RecordHistory::query()->count())->toBe(0);
})->with([
    'closing before opening' => [['is_closed' => false, 'open_time' => '13:00', 'close_time' => '09:00'], 'close_time'],
    'special hours without times' => [['is_closed' => false], 'open_time'],
    'no features' => [['applies_to' => []], 'applies_to'],
    'unknown feature' => [['applies_to' => ['parking']], 'applies_to.0'],
    'no reason' => [['reason' => ''], 'reason'],
]);

it('refuses a date that already has a closure for a chosen feature, adding nothing', function (): void {
    AppointmentScheduleOverride::factory()->for($this->location)->closure()->onDate('2026-12-25')->create();

    $this->actingAs(closuresManager($this->location))
        ->post(route('dashboard.locations.closures.store', ['uuid' => $this->location->uuid]), closurePayload())
        ->assertSessionHasErrors(['applies_to' => 'Appointments already have a closure on this date. Remove it first.']);

    expect($this->location->checkinScheduleOverrides()->count())->toBe(0)
        ->and(RecordHistory::query()->count())->toBe(0);
});

it('returns not found for a location the user is not attached to', function (): void {
    $this->actingAs(closuresManager(Location::factory()->create()))
        ->post(route('dashboard.locations.closures.store', ['uuid' => $this->location->uuid]), closurePayload())
        ->assertNotFound();
});

it('forbids staff who may not edit hours', function (): void {
    $shipping = User::factory()->withRole(Role::Shipping)->worksAt($this->location)->create();

    $this->actingAs($shipping)
        ->post(route('dashboard.locations.closures.store', ['uuid' => $this->location->uuid]), closurePayload())
        ->assertForbidden();

    $this->delete(route('dashboard.locations.closures.destroy', ['uuid' => $this->location->uuid, 'date' => '2026-12-25']), ['applies_to' => ['checkIns']])
        ->assertForbidden();
});

it('removes a closure from the listed features only and logs it on both trails', function (): void {
    CheckInScheduleOverride::factory()->for($this->location)->closure()->onDate('2026-12-25')->create(['reason' => 'Christmas']);
    AppointmentScheduleOverride::factory()->for($this->location)->closure()->onDate('2026-12-25')->create(['reason' => 'Christmas']);
    $manager = closuresManager($this->location);

    $this->actingAs($manager)
        ->delete(route('dashboard.locations.closures.destroy', ['uuid' => $this->location->uuid, 'date' => '2026-12-25']), ['applies_to' => ['checkIns']])
        ->assertInertiaFlash('toast.message', 'Closure on Dec 25 removed.');

    $history = $this->location->history()->sole();

    expect($this->location->checkinScheduleOverrides()->count())->toBe(0)
        ->and($this->location->appointmentScheduleOverrides()->count())->toBe(1)
        ->and($history->event)->toBe(RecordHistoryEvent::ClosureRemoved)
        ->and($history->context)->toBe(['date' => '2026-12-25', 'reason' => 'Christmas', 'appliesTo' => ['checkIns']])
        ->and(UserHistory::query()->sole()->event)->toBe(UserHistoryEvent::LocationClosureRemoved);
});

it('writes nothing when there is no closure to remove', function (): void {
    $this->actingAs(closuresManager($this->location))
        ->delete(route('dashboard.locations.closures.destroy', ['uuid' => $this->location->uuid, 'date' => '2026-12-25']), ['applies_to' => ['checkIns']])
        ->assertInertiaFlash('toast.message', 'No closure to remove.');

    expect(RecordHistory::query()->count())->toBe(0)
        ->and(UserHistory::query()->count())->toBe(0);
});

it('keeps past closures as history', function (): void {
    CheckInScheduleOverride::factory()->for($this->location)->closure()->onDate('2026-11-26')->create();

    $this->actingAs(closuresManager($this->location))
        ->delete(route('dashboard.locations.closures.destroy', ['uuid' => $this->location->uuid, 'date' => '2026-11-26']), ['applies_to' => ['checkIns']])
        ->assertSessionHasErrors(['date' => 'Past closures cannot be removed.']);

    expect($this->location->checkinScheduleOverrides()->count())->toBe(1);
});

it("describes added and removed closures on the location's timeline", function (): void {
    $manager = closuresManager($this->location);
    $uuid = $this->location->uuid;

    $this->actingAs($manager)->post(route('dashboard.locations.closures.store', ['uuid' => $uuid]), closurePayload());
    $this->post(route('dashboard.locations.closures.store', ['uuid' => $uuid]), closurePayload([
        'date' => '2026-12-24',
        'reason' => 'Christmas Eve',
        'is_closed' => false,
        'open_time' => '08:00',
        'close_time' => '12:00',
        'applies_to' => ['checkIns'],
    ]));
    $this->delete(route('dashboard.locations.closures.destroy', ['uuid' => $uuid, 'date' => '2026-12-25']), ['applies_to' => ['appointments']]);

    $this->get(route('dashboard.locations.history', ['uuid' => $uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('history.data.0.description', 'Closure removed for Fri, Dec 25, 2026: Christmas (appointments)')
            ->where('history.data.1.description', 'Special hours 8:00 AM - 12:00 PM added for Thu, Dec 24, 2026: Christmas Eve (check-ins)')
            ->where('history.data.2.description', 'Closure added for Fri, Dec 25, 2026: Christmas (check-ins, appointments)'));
});
