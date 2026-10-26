<?php

declare(strict_types=1);

use App\Appointment\AppointmentScheduleResolver;
use App\Enums\RecordHistoryEvent;
use App\Enums\Role;
use App\Enums\UserHistoryEvent;
use App\Models\Location;
use App\Models\User;
use App\Models\UserHistory;
use App\Schedule\WeeklyScheduleResolver;
use Inertia\Testing\AssertableInertia;

/*
 * Admins pausing and resuming new bookings at a location, from its overview,
 * and what guests see while it is paused.
 */

function appointmentPauseAdmin(): User
{
    return User::factory()->withRole(Role::Admin)->create();
}

it('pauses appointments for a set time in the location timezone and logs it on both trails', function (string $duration, ?string $until): void {
    // 14:00 UTC is 09:00 in New York.
    $this->travelTo('2026-12-02 14:00:00');
    $location = Location::factory()->appointmentsOnly()->create(['name' => 'Alpha Dock', 'timezone' => 'America/New_York']);

    $this->actingAs(appointmentPauseAdmin())
        ->from(route('dashboard.locations.show', ['uuid' => $location->uuid]))
        ->post(route('dashboard.locations.appointmentPause.store', ['uuid' => $location->uuid]), [
            'reason' => 'Calendar is full',
            'duration' => $duration,
        ])
        ->assertRedirect(route('dashboard.locations.show', ['uuid' => $location->uuid]))
        ->assertInertiaFlash('toast.message', 'Appointments at Alpha Dock are paused.');

    $location->refresh();
    $trail = UserHistory::query()->sole();

    expect($location->appointments_paused_reason)->toBe('Calendar is full')
        ->and($location->appointments_paused_at?->toDateTimeString())->toBe('2026-12-02 14:00:00')
        ->and($location->appointments_paused_until?->toDateTimeString())->toBe($until)
        ->and($location->history()->sole()->event)->toBe(RecordHistoryEvent::AppointmentsPaused)
        ->and($trail->event)->toBe(UserHistoryEvent::LocationAppointmentsPaused)
        ->and($trail->changes['location'])->toBe($location->uuid);
})->with([
    'rest of today' => ['rest-of-today', '2026-12-03 05:00:00'],
    '1 day' => ['1d', '2026-12-03 14:00:00'],
    '3 days' => ['3d', '2026-12-05 14:00:00'],
    '1 week' => ['1w', '2026-12-09 14:00:00'],
    'until resumed' => ['until-resumed', null],
]);

it('replaces an active pause, so staff can extend it', function (): void {
    $this->travelTo('2026-12-02 14:00:00');
    $location = Location::factory()->appointmentsOnly()->appointmentsPaused('Short-staffed', now()->addHour())->create();

    $this->actingAs(appointmentPauseAdmin())
        ->post(route('dashboard.locations.appointmentPause.store', ['uuid' => $location->uuid]), [
            'reason' => 'Calendar is full',
            'duration' => 'until-resumed',
        ])
        ->assertSessionHasNoErrors();

    $location->refresh();
    expect($location->appointments_paused_reason)->toBe('Calendar is full')
        ->and($location->appointments_paused_until)->toBeNull();
});

it('requires a reason and a known duration', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();

    $this->actingAs(appointmentPauseAdmin())
        ->post(route('dashboard.locations.appointmentPause.store', ['uuid' => $location->uuid]), ['duration' => '30m'])
        ->assertSessionHasErrors(['reason', 'duration']);

    expect($location->refresh()->appointments_paused_at)->toBeNull();
});

it('forbids staff without pause appointments', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    $manager = User::factory()->withRole(Role::ShippingManager)->worksAt($location)->create();

    $this->actingAs($manager)
        ->post(route('dashboard.locations.appointmentPause.store', ['uuid' => $location->uuid]), [
            'reason' => 'Calendar is full',
            'duration' => '1d',
        ])
        ->assertForbidden();

    expect($location->refresh()->appointments_paused_at)->toBeNull();
});

it('resumes appointments and logs it on both trails', function (): void {
    $location = Location::factory()->appointmentsOnly()->appointmentsPaused()->create(['name' => 'Alpha Dock']);

    $this->actingAs(appointmentPauseAdmin())
        ->delete(route('dashboard.locations.appointmentPause.destroy', ['uuid' => $location->uuid]))
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', 'Appointments at Alpha Dock have resumed.');

    $location->refresh();
    expect($location->appointments_paused_at)->toBeNull()
        ->and($location->appointments_paused_reason)->toBeNull()
        ->and($location->history()->sole()->event)->toBe(RecordHistoryEvent::AppointmentsResumed)
        ->and(UserHistory::query()->sole()->event)->toBe(UserHistoryEvent::LocationAppointmentsResumed);
});

it('clears a pause that already ended without logging a resume', function (): void {
    $this->travelTo('2026-12-02 14:00:00');
    $location = Location::factory()->appointmentsOnly()->appointmentsPaused('Calendar is full', now()->subMinute())->create(['name' => 'Alpha Dock']);

    $this->actingAs(appointmentPauseAdmin())
        ->delete(route('dashboard.locations.appointmentPause.destroy', ['uuid' => $location->uuid]))
        ->assertInertiaFlash('toast.message', 'Appointments at Alpha Dock were not paused.');

    expect($location->refresh()->appointments_paused_at)->toBeNull()
        ->and($location->history()->count())->toBe(0)
        ->and(UserHistory::query()->count())->toBe(0);
});

it('shows an active pause on the overview and history, with its end in the location timezone', function (): void {
    $this->travelTo('2026-12-02 14:00:00');
    $location = Location::factory()->appointmentsOnly()->create(['timezone' => 'America/New_York']);
    $admin = appointmentPauseAdmin();

    $this->actingAs($admin)->post(route('dashboard.locations.appointmentPause.store', ['uuid' => $location->uuid]), [
        'reason' => 'Calendar is full',
        'duration' => '1d',
    ]);

    $this->get(route('dashboard.locations.show', ['uuid' => $location->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('overview.appointments.isOpen', false)
            ->where('overview.appointments.reason', 'Calendar is full')
            ->where('overview.appointments.pause', ['until' => '2026-12-03T09:00:00-05:00', 'reason' => 'Calendar is full']));

    $this->get(route('dashboard.locations.history', ['uuid' => $location->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('history.data.0.description', 'Appointments paused until Dec 3, 9:00 AM (reason: Calendar is full)'));
});

it('offers no slot on any day while paused, and brings them back once the pause ends', function (): void {
    $this->travelTo('2026-12-02 14:00:00');
    $location = Location::factory()->appointmentsOnly()->appointmentsPaused('Calendar is full', now()->addDay())->create();
    $resolver = resolve(AppointmentScheduleResolver::class);
    $nextWeek = now()->addWeek()->setTime(15, 0);

    $paused = $resolver->buildDTO($location, now());

    expect($paused->availability)->toBe([])
        ->and($paused->isOpen)->toBeFalse()
        ->and($paused->reason)->toBe('Calendar is full')
        ->and($resolver->isBookableSlot($location, $nextWeek))->toBeFalse()
        ->and(resolve(WeeklyScheduleResolver::class)->buildWeek($location, now())->isOpenNowAppointment)->toBeFalse();

    $this->travelTo('2026-12-03 14:00:00');

    expect($resolver->buildDTO($location, now())->availability)->not->toBe([])
        ->and($resolver->isBookableSlot($location, $nextWeek))->toBeTrue();
});

it('refuses a booking submitted while paused, with the reason', function (): void {
    $this->travelTo('2026-12-02 08:00:00');
    $location = Location::factory()->appointmentsOnly()->create(['name' => 'Alpha Dock']);
    $this->post(route('appointment.gate', $location->uuid), ['uuid' => $location->uuid]);

    $location->update(['appointments_paused_at' => now(), 'appointments_paused_reason' => 'Calendar is full']);

    $this->post(route('appointment.store', $location->uuid), [
        'datetime' => now()->addDays(2)->setTime(10, 0)->format('Y-m-d H:i:s'),
        'po_numbers' => ['SO-00001'],
        'drivers_name' => 'John Doe',
        'drivers_cellphone' => '(201) 555-0123',
    ])->assertSessionHasErrors([
        'datetime' => 'Alpha Dock is not taking new appointments right now (Calendar is full). Please try again later.',
    ]);
});
