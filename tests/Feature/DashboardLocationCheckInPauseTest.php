<?php

declare(strict_types=1);

use App\Enums\RecordHistoryEvent;
use App\Enums\Role;
use App\Enums\UserHistoryEvent;
use App\Models\Location;
use App\Models\User;
use App\Models\UserHistory;
use App\Schedule\WeeklyScheduleResolver;
use Inertia\Testing\AssertableInertia;

/*
 * Pausing and resuming new check-ins at a location, from its overview.
 */

function pauseManager(Location $location): User
{
    return User::factory()->withRole(Role::ShippingManager)->worksAt($location)->create();
}

it('pauses check-ins for a set time in the location timezone and logs it on both trails', function (string $duration, ?string $until): void {
    // 14:00 UTC is 10:00 in New York; weekday hours there close at 17:00.
    $this->travelTo('2026-12-02 14:00:00');
    $location = Location::factory()->checkinWeekdayWindow()->create(['name' => 'Alpha Dock', 'timezone' => 'America/New_York']);
    $manager = pauseManager($location);

    $this->actingAs($manager)
        ->from(route('dashboard.locations.show', ['uuid' => $location->uuid]))
        ->post(route('dashboard.locations.checkInPause.store', ['uuid' => $location->uuid]), [
            'reason' => 'Yard is full',
            'duration' => $duration,
        ])
        ->assertRedirect(route('dashboard.locations.show', ['uuid' => $location->uuid]))
        ->assertInertiaFlash('toast.message', 'Check-ins at Alpha Dock are paused.');

    $location->refresh();
    $trail = UserHistory::query()->sole();

    expect($location->checkins_paused_reason)->toBe('Yard is full')
        ->and($location->checkins_paused_at?->toDateTimeString())->toBe('2026-12-02 14:00:00')
        ->and($location->checkins_paused_until?->toDateTimeString())->toBe($until)
        ->and($location->history()->sole()->event)->toBe(RecordHistoryEvent::CheckInsPaused)
        ->and($trail->event)->toBe(UserHistoryEvent::LocationCheckInsPaused)
        ->and($trail->changes['location'])->toBe($location->uuid);
})->with([
    '30 minutes' => ['30m', '2026-12-02 14:30:00'],
    '1 hour' => ['1h', '2026-12-02 15:00:00'],
    '2 hours' => ['2h', '2026-12-02 16:00:00'],
    'rest of today' => ['rest-of-today', '2026-12-02 22:00:00'],
    'until resumed' => ['until-resumed', null],
]);

it('replaces an active pause, so staff can extend it', function (): void {
    $this->travelTo('2026-12-02 14:00:00');
    $location = Location::factory()->checkinsPaused('Short-staffed', now()->addMinutes(10))->create();

    $this->actingAs(pauseManager($location))
        ->post(route('dashboard.locations.checkInPause.store', ['uuid' => $location->uuid]), [
            'reason' => 'Yard is full',
            'duration' => 'until-resumed',
        ])
        ->assertSessionHasNoErrors();

    $location->refresh();
    expect($location->checkins_paused_reason)->toBe('Yard is full')
        ->and($location->checkins_paused_until)->toBeNull();
});

it('refuses rest of today once check-ins have closed for the day', function (): void {
    // 23:00 UTC is 18:00 in New York, after the 17:00 close.
    $this->travelTo('2026-12-02 23:00:00');
    $location = Location::factory()->checkinWeekdayWindow()->create(['timezone' => 'America/New_York']);

    $this->actingAs(pauseManager($location))
        ->post(route('dashboard.locations.checkInPause.store', ['uuid' => $location->uuid]), [
            'reason' => 'Yard is full',
            'duration' => 'rest-of-today',
        ])
        ->assertSessionHasErrors(['duration' => 'Check-ins have no closing time left today. Choose another duration.']);

    expect($location->refresh()->checkins_paused_at)->toBeNull();
});

it('requires a reason and a known duration', function (): void {
    $location = Location::factory()->create();

    $this->actingAs(pauseManager($location))
        ->post(route('dashboard.locations.checkInPause.store', ['uuid' => $location->uuid]), ['duration' => 'forever'])
        ->assertSessionHasErrors(['reason', 'duration']);

    expect($location->refresh()->checkins_paused_at)->toBeNull();
});

it('rejects fields outside the pause', function (): void {
    $location = Location::factory()->create();

    $this->actingAs(pauseManager($location))
        ->post(route('dashboard.locations.checkInPause.store', ['uuid' => $location->uuid]), [
            'reason' => 'Yard is full',
            'duration' => '30m',
            'is_checkins_enabled' => false,
        ])
        ->assertSessionHasErrors('is_checkins_enabled');

    expect($location->refresh()->checkins_paused_at)->toBeNull();
});

it('forbids staff without pause check-ins', function (): void {
    $location = Location::factory()->create();
    $shipping = User::factory()->withRole(Role::Shipping)->worksAt($location)->create();

    $this->actingAs($shipping)
        ->post(route('dashboard.locations.checkInPause.store', ['uuid' => $location->uuid]), [
            'reason' => 'Yard is full',
            'duration' => '30m',
        ])
        ->assertForbidden();

    expect($location->refresh()->checkins_paused_at)->toBeNull();
});

it('returns not found for a location the manager does not work at', function (string $method, string $route): void {
    $location = Location::factory()->checkinsPaused()->create();
    $manager = pauseManager(Location::factory()->create());

    $this->actingAs($manager)
        ->{$method}(route($route, ['uuid' => $location->uuid]), ['reason' => 'Yard is full', 'duration' => '30m'])
        ->assertNotFound();

    expect($location->refresh()->checkins_paused_reason)->toBe('Yard is full');
})->with([
    'pause' => ['post', 'dashboard.locations.checkInPause.store'],
    'resume' => ['delete', 'dashboard.locations.checkInPause.destroy'],
]);

it('resumes check-ins and logs it on both trails', function (): void {
    $location = Location::factory()->checkinsPaused()->create(['name' => 'Alpha Dock']);

    $this->actingAs(pauseManager($location))
        ->delete(route('dashboard.locations.checkInPause.destroy', ['uuid' => $location->uuid]))
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', 'Check-ins at Alpha Dock have resumed.');

    $location->refresh();
    expect($location->checkins_paused_at)->toBeNull()
        ->and($location->checkins_paused_reason)->toBeNull()
        ->and($location->history()->sole()->event)->toBe(RecordHistoryEvent::CheckInsResumed)
        ->and(UserHistory::query()->sole()->event)->toBe(UserHistoryEvent::LocationCheckInsResumed);
});

it('clears a pause that already ended without logging a resume', function (): void {
    $this->travelTo('2026-12-02 14:00:00');
    $location = Location::factory()->checkinsPaused('Yard is full', now()->subMinute())->create(['name' => 'Alpha Dock']);

    $this->actingAs(pauseManager($location))
        ->delete(route('dashboard.locations.checkInPause.destroy', ['uuid' => $location->uuid]))
        ->assertInertiaFlash('toast.message', 'Check-ins at Alpha Dock were not paused.');

    expect($location->refresh()->checkins_paused_at)->toBeNull()
        ->and($location->history()->count())->toBe(0)
        ->and(UserHistory::query()->count())->toBe(0);
});

it('shows an active pause on the overview as closed, with its end in the location timezone', function (): void {
    $this->travelTo('2026-12-02 14:00:00');
    $location = Location::factory()->checkinsPaused('Yard is full', now()->addHour())->create(['timezone' => 'America/New_York']);

    $this->actingAs(pauseManager($location))
        ->get(route('dashboard.locations.show', ['uuid' => $location->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('overview.checkIns.isOpen', false)
            ->where('overview.checkIns.reason', 'Yard is full')
            ->where('overview.checkIns.pause', ['until' => '10:00', 'reason' => 'Yard is full']));

    $this->travelTo('2026-12-02 15:00:00');

    $this->get(route('dashboard.locations.show', ['uuid' => $location->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('overview.checkIns.isOpen', true)
            ->where('overview.checkIns.pause', null));
});

it('closes the public schedule now without marking today closed in the week', function (): void {
    $this->travelTo('2026-12-02 14:00:00');
    $location = Location::factory()->checkinsPaused()->create();

    $week = resolve(WeeklyScheduleResolver::class)->buildWeek($location, now());
    $today = collect($week->checkInWeek)->firstWhere('isToday', true);

    expect($week->isOpenNowCheckIn)->toBeFalse()
        ->and($today->isOverrideClosure)->toBeFalse()
        ->and($today->hours)->not->toBeNull();
});
