<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\CheckInErpStatus;
use App\Enums\CheckInStatus;
use App\Enums\Role;
use App\Events\AppointmentCreated;
use App\Events\AppointmentUpdated;
use App\Events\CheckInCreated;
use App\Events\CheckInUpdated;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;

/*
 * The websocket feeds behind the live queue: who may subscribe to the
 * per-user check-in and appointment channels, who each record is pushed to,
 * what gets pushed, and that the payload never carries the full license
 * number.
 */

/**
 * The suite runs on the null broadcaster, which authorises every channel. Swap
 * in Reverb with throwaway credentials and re-register the channels on it.
 */
function useReverbBroadcaster(): void
{
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);

    require base_path('routes/channels.php');
}

function authorizeCheckInFeed(User $user, string $feed = 'check-ins', ?User $owner = null): TestResponse
{
    return test()->actingAs($user)->post('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => "private-dashboard.{$feed}.".($owner ?? $user)->uuid,
    ]);
}

it('lets every staff role subscribe to the check-in feed', function (Role $role): void {
    useReverbBroadcaster();

    authorizeCheckInFeed(User::factory()->withRole($role)->create())
        ->assertSuccessful()
        ->assertJsonStructure(['auth']);
})->with(Role::staff());

it("refuses another staff member's feed", function (string $feed): void {
    useReverbBroadcaster();

    authorizeCheckInFeed(
        User::factory()->withRole(Role::Admin)->create(),
        $feed,
        owner: User::factory()->withRole(Role::Shipping)->create(),
    )->assertForbidden();
})->with(['check-ins', 'appointments']);

it('refuses a feed addressed by numeric id', function (): void {
    useReverbBroadcaster();
    $user = User::factory()->withRole(Role::Admin)->create();

    test()->actingAs($user)->post('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => "private-dashboard.check-ins.{$user->id}",
    ])->assertForbidden();
});

it('authorizes the notification channel by uuid', function (): void {
    useReverbBroadcaster();
    $user = User::factory()->withRole(Role::Admin)->create();

    expect($user->receivesBroadcastNotificationsOn())->toBe("App.Models.User.{$user->uuid}");

    test()->actingAs($user)->post('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => "private-App.Models.User.{$user->uuid}",
    ])->assertSuccessful();

    test()->actingAs($user)->post('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => "private-App.Models.User.{$user->id}",
    ])->assertForbidden();
});

it('pushes a record only to the staff who work its location', function (string $feed, Closure $eventAt): void {
    $location = Location::factory()->create();
    $admin = User::factory()->withRole(Role::Admin)->create();
    $shippingHere = User::factory()->withRole(Role::Shipping)->worksAt($location)->create();
    User::factory()->withRole(Role::Shipping)->worksAt(Location::factory()->create())->create();
    User::factory()->withRole(Role::Shipping)->worksAt($location)->unverified()->create();

    $channels = array_map(fn (PrivateChannel $channel): string => $channel->name, $eventAt($location)->broadcastOn());

    expect($channels)->toEqualCanonicalizing([
        "private-dashboard.{$feed}.{$admin->uuid}",
        "private-dashboard.{$feed}.{$shippingHere->uuid}",
    ]);
})->with([
    'check-in' => ['check-ins', fn (Location $location): CheckInCreated => new CheckInCreated(CheckIn::factory()->atLocation($location)->create())],
    'appointment' => ['appointments', fn (Location $location): AppointmentCreated => new AppointmentCreated(Appointment::factory()->atLocation($location)->create())],
]);

it('refuses the check-in feed to drivers', function (): void {
    useReverbBroadcaster();

    authorizeCheckInFeed(User::factory()->withRole(Role::Driver)->create())->assertForbidden();
});

it('refuses the check-in feed to unverified staff', function (): void {
    useReverbBroadcaster();

    authorizeCheckInFeed(User::factory()->withRole(Role::Shipping)->unverified()->create())->assertForbidden();
});

it('broadcasts status and ERP status changes', function (string $column, BackedEnum $value): void {
    $checkIn = CheckIn::factory()->create();
    Event::fake([CheckInUpdated::class]);

    $checkIn->update([$column => $value]);

    Event::assertDispatched(CheckInUpdated::class, fn (CheckInUpdated $event): bool => $event->checkIn->is($checkIn));
})->with([
    'status' => ['status', CheckInStatus::Completed],
    'erp status' => ['erp_status', CheckInErpStatus::Synced],
]);

it('does not broadcast saves that leave both statuses alone', function (): void {
    $checkIn = CheckIn::factory()->create();
    Event::fake([CheckInUpdated::class]);

    $checkIn->update(['customer' => 'Someone Else']);

    Event::assertNotDispatched(CheckInUpdated::class);
});

it('pushes only the last four digits of the license', function (): void {
    $checkIn = CheckIn::factory()->create(['drivers_license_number' => 'D12345678']);

    $payload = new CheckInCreated($checkIn)->broadcastWith();

    expect($payload['checkIn']['driver']['licenseLastFour'])->toBe('5678')
        ->and(json_encode($payload))->not->toContain('D12345678');
});

it('lets every staff role subscribe to the appointment feed', function (Role $role): void {
    useReverbBroadcaster();

    authorizeCheckInFeed(User::factory()->withRole($role)->create(), 'appointments')
        ->assertSuccessful()
        ->assertJsonStructure(['auth']);
})->with(Role::staff());

it('refuses the appointment feed to drivers and unverified staff', function (User $user): void {
    useReverbBroadcaster();

    authorizeCheckInFeed($user, 'appointments')->assertForbidden();
})->with([
    'driver' => fn (): User => User::factory()->withRole(Role::Driver)->create(),
    'unverified staff' => fn (): User => User::factory()->withRole(Role::Shipping)->unverified()->create(),
]);

it('broadcasts appointment status and time changes', function (array $changes): void {
    $appointment = Appointment::factory()->scheduled()->create();
    Event::fake([AppointmentUpdated::class]);

    $appointment->update($changes);

    Event::assertDispatched(AppointmentUpdated::class, fn (AppointmentUpdated $event): bool => $event->appointment->is($appointment));
})->with([
    'status' => fn (): array => ['status' => AppointmentStatus::Cancelled],
    'scheduled time' => fn (): array => ['scheduled_for' => now()->addDays(2)],
]);

it('does not broadcast appointment saves that leave status and time alone', function (): void {
    $appointment = Appointment::factory()->scheduled()->create();
    Event::fake([AppointmentUpdated::class]);

    $appointment->update(['drivers_name' => 'Someone Else']);

    Event::assertNotDispatched(AppointmentUpdated::class);
});
