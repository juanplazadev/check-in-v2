<?php

declare(strict_types=1);

use App\Enums\AppointmentLinkMethod;
use App\Enums\AppointmentStatus;
use App\Enums\RecordHistoryEvent;
use App\Enums\Role;
use App\Enums\UserHistoryEvent;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\RecordHistory;
use App\Models\User;
use App\Models\UserHistory;
use Illuminate\Support\Facades\Date;

/*
 * Linking a walk-in check-in to its booking from the detail page: the ranked
 * suggestions, the reference-number fallback, the scoping that keeps either
 * from reaching another location's bookings, and undoing a staff link.
 */

/**
 * @return array{0: Location, 1: CheckIn}
 */
function linkableCheckIn(): array
{
    $location = Location::factory()->create();
    $checkIn = CheckIn::factory()->atLocation($location)->pending()->create([
        'drivers_name' => 'Maria Gonzalez',
        'drivers_cellphone' => '+17875550142',
    ]);

    return [$location, $checkIn];
}

it('suggests same-location bookings that share the phone or name, phone matches first', function (): void {
    [$location, $checkIn] = linkableCheckIn();
    $nameOnly = Appointment::factory()->atLocation($location)->create([
        'drivers_name' => 'Maria Gonzales',
        'drivers_cellphone' => '+17875559999',
        'scheduled_for' => Date::now()->addMinutes(5),
    ]);
    $phone = Appointment::factory()->atLocation($location)->noShow()->create([
        'drivers_name' => 'M. G.',
        'drivers_cellphone' => '+17875550142',
        'scheduled_for' => Date::now()->subHours(3),
    ]);
    Appointment::factory()->atLocation($location)->create([
        'drivers_name' => 'John Smith',
        'drivers_cellphone' => '+17875551111',
    ]);

    $this->actingAs(User::factory()->withRole(Role::ShippingManager)->worksAt($location)->create())
        ->getJson(route('dashboard.checkIns.appointment.index', ['uuid' => $checkIn->uuid]))
        ->assertOk()
        ->assertJsonCount(2, 'suggestions')
        ->assertJsonPath('suggestions.0.uuid', $phone->uuid)
        ->assertJsonPath('suggestions.0.phoneMatch', true)
        ->assertJsonPath('suggestions.0.cellphoneLastFour', '0142')
        ->assertJsonPath('suggestions.1.uuid', $nameOnly->uuid)
        ->assertJsonPath('suggestions.1.phoneMatch', false)
        ->assertJsonPath('suggestions.1.nameMatch', true);
});

it('leaves out bookings that cannot be linked', function (): void {
    [$location, $checkIn] = linkableCheckIn();
    $match = ['drivers_cellphone' => '+17875550142'];

    Appointment::factory()->create($match);
    Appointment::factory()->atLocation($location)->cancelled()->create($match);
    Appointment::factory()->atLocation($location)->completed()->create($match);
    Appointment::factory()->atLocation($location)->create([...$match, 'scheduled_for' => Date::now()->addDays(2)]);
    Appointment::factory()->atLocation($location)->create($match)->delete();
    CheckIn::factory()->atLocation($location)->linkedToAppointment(Appointment::factory()->atLocation($location)->create($match))->create();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->getJson(route('dashboard.checkIns.appointment.index', ['uuid' => $checkIn->uuid]))
        ->assertOk()
        ->assertJsonCount(0, 'suggestions');
});

it('links a suggested booking and logs it on both timelines and the staff trail', function (): void {
    [$location, $checkIn] = linkableCheckIn();
    $appointment = Appointment::factory()->atLocation($location)->create();
    $staff = User::factory()->withRole(Role::ShippingManager)->worksAt($location)->create();

    $this->actingAs($staff)
        ->from(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]))
        ->post(route('dashboard.checkIns.appointment.store', ['uuid' => $checkIn->uuid]), ['appointment_uuid' => $appointment->uuid])
        ->assertRedirect(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]));

    $checkIn->refresh();
    expect($checkIn->appointment_id)->toBe($appointment->id)
        ->and($checkIn->appointment_linked_via)->toBe(AppointmentLinkMethod::Staff)
        ->and($checkIn->appointment_linked_by)->toBe($staff->id)
        ->and($checkIn->appointment_linked_at)->not->toBeNull()
        ->and($appointment->refresh()->status)->toBe(AppointmentStatus::CheckedIn);

    $linked = RecordHistory::query()->where('event', RecordHistoryEvent::AppointmentLinked)->get();
    expect($linked)->toHaveCount(2)
        ->and($linked->firstWhere('recordable_type', 'check_in')->context)->toBe(['reference_number' => $appointment->reference_number])
        ->and($linked->firstWhere('recordable_type', 'appointment')->context)->toBe(['reference_number' => $checkIn->reference_number])
        ->and($linked->pluck('user_id')->unique()->all())->toBe([$staff->id]);

    $trail = UserHistory::query()->sole();
    expect($trail->event)->toBe(UserHistoryEvent::CheckInAppointmentLinked)
        ->and($trail->changes)->toBe(['check_in' => $checkIn->uuid, 'appointment' => $appointment->uuid]);
});

it('links by reference number, ignoring case and spaces', function (): void {
    [$location, $checkIn] = linkableCheckIn();
    $appointment = Appointment::factory()->atLocation($location)->create(['scheduled_for' => Date::now()->addWeek()]);
    $typed = ' '.mb_strtolower(mb_substr($appointment->reference_number, 0, 4)).' '.mb_substr($appointment->reference_number, 4);

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.checkIns.appointment.store', ['uuid' => $checkIn->uuid]), ['reference_number' => $typed])
        ->assertSessionHasNoErrors();

    expect($checkIn->refresh()->appointment_id)->toBe($appointment->id);
});

it('gives the same answer for a reference that is unknown or not linkable here', function (callable $reference): void {
    [, $checkIn] = linkableCheckIn();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.checkIns.appointment.store', ['uuid' => $checkIn->uuid]), ['reference_number' => $reference()])
        ->assertSessionHasErrors(['reference_number' => 'No linkable appointment with that reference at this location.']);

    expect($checkIn->refresh()->appointment_id)->toBeNull()
        ->and(UserHistory::query()->count())->toBe(0);
})->with([
    'unknown' => [fn (): string => 'AZZZZZZZ'],
    'another location' => [fn (): string => Appointment::factory()->create()->reference_number],
    'cancelled' => [fn (): string => Appointment::factory()->atLocation(CheckIn::query()->sole()->location)->cancelled()->create()->reference_number],
]);

it('refuses to link a check-in that is already linked', function (): void {
    [$location, $checkIn] = linkableCheckIn();
    $checkIn->forceFill(['appointment_id' => Appointment::factory()->atLocation($location)->checkedIn()->create()->id])->save();
    $other = Appointment::factory()->atLocation($location)->create();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.checkIns.appointment.store', ['uuid' => $checkIn->uuid]), ['appointment_uuid' => $other->uuid])
        ->assertSessionHasErrors(['appointment' => 'This check-in is already linked to an appointment.']);

    expect($other->refresh()->status)->toBe(AppointmentStatus::Scheduled);
});

it('forbids roles without the permission', function (Role $role): void {
    [$location, $checkIn] = linkableCheckIn();
    $appointment = Appointment::factory()->atLocation($location)->create();
    $user = User::factory()->withRole($role)->worksAt($location)->create();

    $this->actingAs($user)
        ->getJson(route('dashboard.checkIns.appointment.index', ['uuid' => $checkIn->uuid]))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('dashboard.checkIns.appointment.store', ['uuid' => $checkIn->uuid]), ['appointment_uuid' => $appointment->uuid])
        ->assertForbidden();

    expect($checkIn->refresh()->appointment_id)->toBeNull();
})->with([
    'shipping' => [Role::Shipping],
    'sales' => [Role::Sales],
    'driver' => [Role::Driver],
]);

it('returns not found for a check-in at a location the user does not work', function (): void {
    [, $checkIn] = linkableCheckIn();
    $manager = User::factory()->withRole(Role::ShippingManager)->worksAt(Location::factory()->create())->create();

    $this->actingAs($manager)
        ->getJson(route('dashboard.checkIns.appointment.index', ['uuid' => $checkIn->uuid]))
        ->assertNotFound();
});

it('unlinks a staff link and puts the booking back to scheduled', function (): void {
    [$location, $checkIn] = linkableCheckIn();
    $staff = User::factory()->withRole(Role::ShippingManager)->worksAt($location)->create();
    $appointment = Appointment::factory()->atLocation($location)->checkedIn()->create();
    $checkIn->forceFill([
        'appointment_id' => $appointment->id,
        'appointment_linked_at' => Date::now(),
        'appointment_linked_via' => AppointmentLinkMethod::Staff,
        'appointment_linked_by' => $staff->id,
    ])->save();

    $this->actingAs($staff)
        ->delete(route('dashboard.checkIns.appointment.destroy', ['uuid' => $checkIn->uuid]))
        ->assertSessionHasNoErrors();

    $checkIn->refresh();
    expect($checkIn->appointment_id)->toBeNull()
        ->and($checkIn->appointment_linked_at)->toBeNull()
        ->and($checkIn->appointment_linked_via)->toBeNull()
        ->and($checkIn->appointment_linked_by)->toBeNull()
        ->and($appointment->refresh()->status)->toBe(AppointmentStatus::Scheduled)
        ->and(RecordHistory::query()->where('event', RecordHistoryEvent::AppointmentUnlinked)->count())->toBe(2)
        ->and(UserHistory::query()->sole()->event)->toBe(UserHistoryEvent::CheckInAppointmentUnlinked);
});

it('refuses to unlink a booking the driver converted', function (): void {
    [$location, $checkIn] = linkableCheckIn();
    $appointment = Appointment::factory()->atLocation($location)->checkedIn()->create();
    $checkIn->forceFill([
        'appointment_id' => $appointment->id,
        'appointment_linked_at' => Date::now(),
        'appointment_linked_via' => AppointmentLinkMethod::Conversion,
    ])->save();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->delete(route('dashboard.checkIns.appointment.destroy', ['uuid' => $checkIn->uuid]))
        ->assertSessionHasErrors(['appointment' => 'Only appointments linked by staff can be unlinked.']);

    expect($checkIn->refresh()->appointment_id)->toBe($appointment->id)
        ->and($appointment->refresh()->status)->toBe(AppointmentStatus::CheckedIn);
});
