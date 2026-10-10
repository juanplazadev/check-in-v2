<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\User;
use App\Notifications\AppointmentReceived;
use App\Notifications\CheckInReceived;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia;

/*
 * The dashboard header bell: who a new check-in lands with, the shared prop
 * that feeds the bell, marking read, and the 30-day prune. Who a submitted
 * check-in notifies is pinned in CheckInFormTest.
 */

function bellNotificationFor(User $user, ?CheckIn $checkIn = null): string
{
    $user->notifyNow(new CheckInReceived($checkIn ?? CheckIn::factory()->create()), ['database']);

    return $user->notifications()->latest()->value('id');
}

it('stores the check-in summary the bell renders', function (): void {
    $user = User::factory()->withRole(Role::Shipping)->create();
    $checkIn = CheckIn::factory()->create(['drivers_name' => 'John Driver']);

    bellNotificationFor($user, $checkIn);

    $notification = $user->notifications()->sole();

    expect($notification->type)->toBe(CheckInReceived::TYPE)
        ->and($notification->data)->toBe([
            'checkInUuid' => $checkIn->uuid,
            'referenceNumber' => $checkIn->reference_number,
            'driverName' => 'John Driver',
            'locationName' => $checkIn->location->name,
        ]);
});

it("stores the appointment summary the bell renders, in the location's timezone", function (): void {
    $user = User::factory()->withRole(Role::Shipping)->create();
    $appointment = Appointment::factory()
        ->atLocation(Location::factory()->create(['timezone' => 'America/Phoenix']))
        ->scheduledFor(Date::parse('2026-10-12 21:30:00', 'UTC'))
        ->create(['drivers_name' => 'Jane Driver']);

    $user->notifyNow(new AppointmentReceived($appointment), ['database']);

    $notification = $user->notifications()->sole();

    expect($notification->type)->toBe(AppointmentReceived::TYPE)
        ->and($notification->data)->toBe([
            'appointmentUuid' => $appointment->uuid,
            'referenceNumber' => $appointment->reference_number,
            'driverName' => 'Jane Driver',
            'locationName' => $appointment->location->name,
            'scheduledFor' => 'Oct 12, 2:30 PM',
        ]);
});

it('marks an appointment notification read and opens the appointment', function (): void {
    $user = User::factory()->withRole(Role::Shipping)->create();
    $appointment = Appointment::factory()->create();
    $user->notifyNow(new AppointmentReceived($appointment), ['database']);

    $this->actingAs($user)
        ->post(route('dashboard.notifications.read', $user->notifications()->value('id')))
        ->assertRedirect(route('dashboard.appointments.show', $appointment->uuid));

    expect($user->unreadNotifications()->count())->toBe(0);
});

it("shares the signed-in user's notifications and unread count on dashboard pages", function (): void {
    $user = User::factory()->withRole(Role::Shipping)->create();
    $read = bellNotificationFor($user);
    bellNotificationFor($user);
    $user->notifications()->whereKey($read)->sole()->markAsRead();
    bellNotificationFor(User::factory()->withRole(Role::Shipping)->create());

    $this->actingAs($user)
        ->get(route('dashboard.queue'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('notifications.unreadCount', 1)
            ->has('notifications.items', 2));
});

it('does not share notifications outside the dashboard', function (): void {
    $user = User::factory()->withRole(Role::Admin)->create();
    bellNotificationFor($user);

    $this->actingAs($user)
        ->get(route('account'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('notifications', null));
});

it('marks a notification read and opens its check-in', function (): void {
    $user = User::factory()->withRole(Role::Shipping)->create();
    $checkIn = CheckIn::factory()->create();
    $id = bellNotificationFor($user, $checkIn);

    $this->actingAs($user)
        ->post(route('dashboard.notifications.read', $id))
        ->assertRedirect(route('dashboard.checkIns.show', $checkIn->uuid));

    expect($user->unreadNotifications()->count())->toBe(0);
});

it("returns 404 when marking another user's notification read", function (): void {
    $owner = User::factory()->withRole(Role::Shipping)->create();
    $id = bellNotificationFor($owner);

    $this->actingAs(User::factory()->withRole(Role::Shipping)->create())
        ->post(route('dashboard.notifications.read', $id))
        ->assertNotFound();

    expect($owner->unreadNotifications()->count())->toBe(1);
});

it("marks only the signed-in user's notifications read with mark all", function (): void {
    $user = User::factory()->withRole(Role::Shipping)->create();
    $other = User::factory()->withRole(Role::Shipping)->create();
    bellNotificationFor($user);
    bellNotificationFor($user);
    bellNotificationFor($other);

    $this->actingAs($user)
        ->from(route('dashboard.queue'))
        ->post(route('dashboard.notifications.readAll'))
        ->assertRedirect(route('dashboard.queue'));

    expect($user->unreadNotifications()->count())->toBe(0)
        ->and($other->unreadNotifications()->count())->toBe(1);
});

it('refuses the notification endpoints to drivers', function (): void {
    $this->actingAs(User::factory()->withRole(Role::Driver)->create())
        ->post(route('dashboard.notifications.readAll'))
        ->assertForbidden();
});

it('prunes notifications older than 30 days', function (): void {
    $user = User::factory()->withRole(Role::Shipping)->create();
    $this->travelTo(now()->subDays(31));
    bellNotificationFor($user);
    $this->travelBack();
    $recent = bellNotificationFor($user);

    $this->artisan('schedule:test', ['--name' => 'notifications:prune'])->assertSuccessful();

    expect($user->notifications()->pluck('id')->all())->toBe([$recent]);
});
