<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\RecordHistoryEvent;
use App\Enums\Role;
use App\Enums\UserHistoryEvent;
use App\Events\AppointmentUpdated;
use App\Mail\AppointmentRescheduledMail;
use App\Models\Appointment;
use App\Models\AppointmentScheduleOverride;
use App\Models\Dock;
use App\Models\LoadType;
use App\Models\Location;
use App\Models\User;
use App\Models\UserHistory;
use App\Notifications\AppointmentRescheduled;
use Carbon\CarbonInterface;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

/*
 * Staff dropping a booking on the dock view: a new time, a new dock, or both.
 */

function dockMover(Location $location): User
{
    return User::factory()->withRole(Role::Shipping)->worksAt($location)->create();
}

/** Tomorrow at a local time, on the 30-minute slot grid. */
function moveSlot(Location $location, int $hour, int $minute = 0): CarbonInterface
{
    return Date::now($location->timezone)->addDay()->setTime($hour, $minute);
}

function moveAppointmentTo(User $user, Appointment $appointment, CarbonInterface $start, ?Dock $dock): Illuminate\Testing\TestResponse
{
    return test()->actingAs($user)->patch(route('dashboard.appointments.move', $appointment->uuid), [
        'scheduled_for' => $start->format('Y-m-d H:i:s'),
        'dock' => $dock?->uuid,
    ]);
}

beforeEach(function (): void {
    Notification::fake();
});

it('moves the time, keeps the length, logs it on both trails and tells the driver', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    $driver = User::factory()->create();
    $appointment = Appointment::factory()->for($location)->forUser($driver)->scheduledFor(moveSlot($location, 8))->lasting(90)->create();
    $target = moveSlot($location, 10, 30);

    moveAppointmentTo(dockMover($location), $appointment, $target, null)->assertSessionHasNoErrors();

    $appointment->refresh();

    expect($appointment->scheduled_for->equalTo($target))->toBeTrue()
        ->and($appointment->ends_at->equalTo($target->copy()->addMinutes(90)))->toBeTrue()
        ->and($appointment->history()->where('event', RecordHistoryEvent::Rescheduled)->sole()->context['scheduled_for'])->toHaveKeys(['from', 'to'])
        ->and(UserHistory::query()->sole()->event)->toBe(UserHistoryEvent::AppointmentRescheduled);

    Notification::assertSentTo($driver, AppointmentRescheduled::class);
});

it('texts a guest when the time moves', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    $appointment = Appointment::factory()->for($location)->scheduledFor(moveSlot($location, 8))->create(['user_id' => null]);

    moveAppointmentTo(dockMover($location), $appointment, moveSlot($location, 9), null)->assertSessionHasNoErrors();

    Notification::assertSentTo(
        new AnonymousNotifiable,
        AppointmentRescheduled::class,
        fn (AppointmentRescheduled $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['sms'] === $appointment->drivers_cellphone,
    );
});

it('moves only the dock without telling the driver or resetting the reminder', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    $dock = Dock::factory()->for($location)->create();
    $staff = dockMover($location);
    $start = moveSlot($location, 8);
    $appointment = Appointment::factory()->for($location)->scheduledFor($start)->reminded()->create();

    moveAppointmentTo($staff, $appointment, $start, $dock)->assertSessionHasNoErrors();

    $appointment->refresh();

    expect($appointment->dock_id)->toBe($dock->id)
        ->and($appointment->dock_assigned_by)->toBe($staff->id)
        ->and($appointment->reminder_sent_at)->not->toBeNull()
        ->and($appointment->history()->where('event', RecordHistoryEvent::DockAssigned)->count())->toBe(1);

    Notification::assertNothingSent();
});

it('moves back to unassigned', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    $dock = Dock::factory()->for($location)->create();
    $start = moveSlot($location, 8);
    $appointment = Appointment::factory()->for($location)->scheduledFor($start)->create(['dock_id' => $dock->id]);

    moveAppointmentTo(dockMover($location), $appointment, $start, null)->assertSessionHasNoErrors();

    expect($appointment->refresh()->dock_id)->toBeNull()
        ->and($appointment->history()->where('event', RecordHistoryEvent::DockUnassigned)->count())->toBe(1);
});

it('moves onto a dock that is only busy at the old time', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    $dockA = Dock::factory()->for($location)->create();
    $dockB = Dock::factory()->for($location)->create();
    $appointment = Appointment::factory()->for($location)->scheduledFor(moveSlot($location, 8))->create(['dock_id' => $dockA->id]);
    Appointment::factory()->for($location)->scheduledFor(moveSlot($location, 8))->create(['dock_id' => $dockB->id]);
    $target = moveSlot($location, 11);

    moveAppointmentTo(dockMover($location), $appointment, $target, $dockB)->assertSessionHasNoErrors();

    $appointment->refresh();

    expect($appointment->dock_id)->toBe($dockB->id)
        ->and($appointment->scheduled_for->equalTo($target))->toBeTrue();
});

it('refuses a dock another booking holds at the new time', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    $dockA = Dock::factory()->for($location)->create();
    $dockB = Dock::factory()->for($location)->create(['name' => 'Door 2']);
    $start = moveSlot($location, 8);
    $appointment = Appointment::factory()->for($location)->scheduledFor($start)->create(['dock_id' => $dockA->id]);
    Appointment::factory()->for($location)->scheduledFor(moveSlot($location, 11))->create(['dock_id' => $dockB->id, 'reference_number' => 'APT22222']);

    moveAppointmentTo(dockMover($location), $appointment, moveSlot($location, 11), $dockB)
        ->assertSessionHasErrors(['move' => 'APT22222 already holds Door 2 during that time.']);

    expect($appointment->refresh()->scheduled_for->equalTo($start))->toBeTrue()
        ->and($appointment->dock_id)->toBe($dockA->id);

    Notification::assertNothingSent();
});

it('refuses a dock that takes no appointments', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    $closed = Dock::factory()->for($location)->create(['accepts_appointments' => false]);
    $start = moveSlot($location, 8);
    $appointment = Appointment::factory()->for($location)->scheduledFor($start)->create();

    moveAppointmentTo(dockMover($location), $appointment, $start, $closed)
        ->assertSessionHasErrors(['move' => "Choose an open dock at this appointment's location."]);
});

it('refuses a time when every dock is booked', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    Dock::factory()->for($location)->create();
    $appointment = Appointment::factory()->for($location)->scheduledFor(moveSlot($location, 8))->create();
    Appointment::factory()->for($location)->scheduledFor(moveSlot($location, 11))->create();

    moveAppointmentTo(dockMover($location), $appointment, moveSlot($location, 11), null)
        ->assertSessionHasErrors(['move' => 'Every dock is booked during that time.']);
});

it('refuses a time that does not fit the hours', function (Closure $target): void {
    $location = Location::factory()->appointmentsOnly()->create();
    $appointment = Appointment::factory()->for($location)->scheduledFor(moveSlot($location, 8))->lasting(180)->create();

    moveAppointmentTo(dockMover($location), $appointment, $target($location), null)
        ->assertSessionHasErrors(['move' => "Choose a slot inside this location's hours."]);
})->with([
    // The factory's schedule closes at 23:59:59.
    'past closing' => [fn (Location $location): CarbonInterface => moveSlot($location, 22)],
    'off the slot grid' => [fn (Location $location): CarbonInterface => moveSlot($location, 9, 7)],
]);

it('refuses a day the location is closed', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    $appointment = Appointment::factory()->for($location)->scheduledFor(moveSlot($location, 8))->create();
    $closed = moveSlot($location, 9)->addDay();
    AppointmentScheduleOverride::factory()->for($location)->create(['override_date' => $closed->toDateString(), 'is_closed' => true]);

    moveAppointmentTo(dockMover($location), $appointment, $closed, null)
        ->assertSessionHasErrors(['move' => "Choose a slot inside this location's hours."]);
});

it('refuses a time that has passed', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    $appointment = Appointment::factory()->for($location)->scheduledFor(moveSlot($location, 8))->create();

    moveAppointmentTo(dockMover($location), $appointment, moveSlot($location, 8)->subDays(3), null)
        ->assertSessionHasErrors(['move' => 'Choose a time that has not passed.']);
});

it('allows staff past the limits that apply to drivers', function (): void {
    $location = Location::factory()->appointmentsOnly()->appointmentsPaused()->create();
    $appointment = Appointment::factory()->for($location)->scheduledFor(moveSlot($location, 8))->create();
    $target = moveSlot($location, 8)->addDays(400);

    moveAppointmentTo(dockMover($location), $appointment, $target, null)->assertSessionHasNoErrors();

    expect($appointment->refresh()->scheduled_for->equalTo($target))->toBeTrue();
});

it('keeps the booked length after the load type gets longer', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    $loadType = LoadType::factory()->for($location)->lasting(60)->create();
    $appointment = Appointment::factory()->scheduledFor(moveSlot($location, 8))->ofLoadType($loadType)->create();
    $loadType->forceFill(['duration_minutes' => 240])->save();
    $target = moveSlot($location, 10);

    moveAppointmentTo(dockMover($location), $appointment, $target, null)->assertSessionHasNoErrors();

    expect($appointment->refresh()->ends_at->equalTo($target->copy()->addMinutes(60)))->toBeTrue();
});

it('lets a checked-in booking change dock but not time', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    $dock = Dock::factory()->for($location)->create();
    $start = moveSlot($location, 8);
    $appointment = Appointment::factory()->for($location)->scheduledFor($start)->checkedIn()->create();
    $staff = dockMover($location);

    moveAppointmentTo($staff, $appointment, moveSlot($location, 9), null)
        ->assertSessionHasErrors(['move' => 'This truck has already arrived, so only its dock can change.']);

    moveAppointmentTo($staff, $appointment, $start, $dock)->assertSessionHasNoErrors();

    expect($appointment->refresh()->dock_id)->toBe($dock->id)
        ->and($appointment->scheduled_for->equalTo($start))->toBeTrue();
});

it('refuses a finished booking', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    $appointment = Appointment::factory()->for($location)->scheduledFor(moveSlot($location, 8))->completed()->create();

    moveAppointmentTo(dockMover($location), $appointment, moveSlot($location, 9), null)
        ->assertSessionHasErrors(['move' => 'Only a scheduled or checked-in appointment can move.']);
});

it('clears a sent reminder when the new reminder moment is still ahead', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    $appointment = Appointment::factory()->for($location)->scheduledFor(moveSlot($location, 8))->reminded()->create();

    moveAppointmentTo(dockMover($location), $appointment, moveSlot($location, 8)->addDays(5), null)->assertSessionHasNoErrors();

    expect($appointment->refresh()->reminder_sent_at)->toBeNull();
});

it('stamps the reminder when the new reminder moment has passed, so the notice is not followed by one', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    // Late in the evening, tomorrow's reminder moment (today at the configured hour) is gone.
    Date::setTestNow(Date::now($location->timezone)->setTime(23, 0));
    $appointment = Appointment::factory()->for($location)->scheduledFor(moveSlot($location, 8)->addDays(5))->create();

    moveAppointmentTo(dockMover($location), $appointment, moveSlot($location, 8), null)->assertSessionHasNoErrors();

    expect($appointment->refresh()->reminder_sent_at)->not->toBeNull();
});

it('broadcasts dock changes to open dashboards', function (): void {
    Event::fake([AppointmentUpdated::class]);
    $location = Location::factory()->appointmentsOnly()->create();
    $dock = Dock::factory()->for($location)->create();
    $start = moveSlot($location, 8);
    $appointment = Appointment::factory()->for($location)->scheduledFor($start)->create();

    moveAppointmentTo(dockMover($location), $appointment, $start, $dock)->assertSessionHasNoErrors();

    Event::assertDispatched(AppointmentUpdated::class);
});

it('requires reschedule permission for a time change and dock permission for a dock change', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    $dock = Dock::factory()->for($location)->create();
    $start = moveSlot($location, 8);
    $appointment = Appointment::factory()->for($location)->scheduledFor($start)->create();

    $withoutReschedule = dockMover($location);
    $withoutReschedule->givePermissionTo(Permission::AssignAppointmentDocks, Permission::ViewAppointments);
    $withoutReschedule->roles()->detach();

    $withoutDocks = dockMover($location);
    $withoutDocks->givePermissionTo(Permission::RescheduleAppointments, Permission::ViewAppointments);
    $withoutDocks->roles()->detach();

    moveAppointmentTo($withoutReschedule->fresh(), $appointment, moveSlot($location, 9), null)->assertForbidden();
    moveAppointmentTo($withoutDocks->fresh(), $appointment, $start, $dock)->assertForbidden();
});

it('forbids staff with neither permission', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    $appointment = Appointment::factory()->for($location)->scheduledFor(moveSlot($location, 8))->create();

    moveAppointmentTo(User::factory()->withRole(Role::Sales)->create(), $appointment, moveSlot($location, 9), null)->assertForbidden();
});

it('hides an appointment at a location the user is not attached to', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    $appointment = Appointment::factory()->for($location)->scheduledFor(moveSlot($location, 8))->create();

    moveAppointmentTo(dockMover(Location::factory()->create()), $appointment, moveSlot($location, 9), null)->assertNotFound();
});

it('rejects a malformed time', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    $appointment = Appointment::factory()->for($location)->scheduledFor(moveSlot($location, 8))->create();

    $this->actingAs(dockMover($location))
        ->patch(route('dashboard.appointments.move', $appointment->uuid), ['scheduled_for' => 'tomorrow', 'dock' => null])
        ->assertSessionHasErrors('scheduled_for');
});

it('tells the driver the new and old times in their language', function (): void {
    $location = Location::factory()->appointmentsOnly()->create(['name' => 'Pompano']);
    $start = moveSlot($location, 8);
    $appointment = Appointment::factory()->for($location)->scheduledFor(moveSlot($location, 10))->create(['locale' => 'es', 'reference_number' => 'APT33333']);

    app()->setLocale('es');
    $mail = (new AppointmentRescheduledMail($appointment, $start))->render();
    $sms = (new AppointmentRescheduled($appointment, $start))->toSms(Notification::route('sms', '+15555550100'));

    expect($mail)->toContain('Tu cita tiene un nuevo horario')
        ->toContain('Horario anterior')
        ->toContain($start->format('g:i A T'))
        ->and($sms->to)->toBe('+15555550100')
        ->and($sms->body)->toContain('APT33333')
        ->toContain('10:00 AM')
        ->not->toContain('http');
});
