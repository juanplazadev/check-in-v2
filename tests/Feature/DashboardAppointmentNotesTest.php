<?php

declare(strict_types=1);

use App\Enums\RecordHistoryEvent;
use App\Enums\Role;
use App\Enums\UserHistoryEvent;
use App\Models\Appointment;
use App\Models\AppointmentNote;
use App\Models\Location;
use App\Models\RecordHistory;
use App\Models\User;
use App\Models\UserHistory;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia;

/*
 * Internal staff notes on an appointment: who may leave one, the two trails
 * each note writes, and how the detail page shows them.
 */

it('stores a staff note and logs it on the appointment timeline and the author trail', function (): void {
    $staff = User::factory()->withRole(Role::Admin)->create();
    $appointment = Appointment::factory()->create();

    $this->actingAs($staff)
        ->from(route('dashboard.appointments.show', ['uuid' => $appointment->uuid]))
        ->post(route('dashboard.appointments.notes.store', ['uuid' => $appointment->uuid]), ['body' => 'Driver will be late.'])
        ->assertRedirect(route('dashboard.appointments.show', ['uuid' => $appointment->uuid]));

    $note = AppointmentNote::query()->sole();
    expect($note->appointment_id)->toBe($appointment->id)
        ->and($note->user_id)->toBe($staff->id)
        ->and($note->body)->toBe('Driver will be late.');

    $entry = RecordHistory::query()->where('event', RecordHistoryEvent::NoteAdded)->sole();
    expect($entry->recordable_type)->toBe($appointment->getMorphClass())
        ->and($entry->recordable_id)->toBe($appointment->id)
        ->and($entry->user_id)->toBe($staff->id)
        ->and($entry->context)->toBeNull();

    $trail = UserHistory::query()->sole();
    expect($trail->user_id)->toBe($staff->id)
        ->and($trail->event)->toBe(UserHistoryEvent::AppointmentNoteAdded)
        ->and($trail->changes)->toBe(['appointment' => $appointment->uuid]);
});

it('lets every staff role leave a note', function (Role $role): void {
    $location = Location::factory()->create();
    $appointment = Appointment::factory()->for($location)->create();

    $this->actingAs(User::factory()->withRole($role)->worksAt($location)->create())
        ->post(route('dashboard.appointments.notes.store', ['uuid' => $appointment->uuid]), ['body' => 'Hello'])
        ->assertRedirect();

    expect(AppointmentNote::query()->count())->toBe(1);
})->with([Role::Shipping, Role::ShippingManager, Role::Sales]);

it('lets staff note an archived appointment', function (): void {
    $appointment = Appointment::factory()->create();
    $appointment->delete();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.appointments.notes.store', ['uuid' => $appointment->uuid]), ['body' => 'Archived by mistake.'])
        ->assertRedirect();

    expect(AppointmentNote::query()->sole()->appointment_id)->toBe($appointment->id);
});

it('forbids a driver from leaving a note', function (): void {
    $appointment = Appointment::factory()->create();

    $this->actingAs(User::factory()->withRole(Role::Driver)->create())
        ->post(route('dashboard.appointments.notes.store', ['uuid' => $appointment->uuid]), ['body' => 'Hello'])
        ->assertForbidden();

    expect(AppointmentNote::query()->count())->toBe(0);
});

it('sends a guest to sign in before leaving a note', function (): void {
    $appointment = Appointment::factory()->create();

    $this->post(route('dashboard.appointments.notes.store', ['uuid' => $appointment->uuid]), ['body' => 'Hello'])
        ->assertRedirect(route('login'));

    expect(AppointmentNote::query()->count())->toBe(0);
});

it('returns not found for an appointment at a location the user does not work', function (): void {
    $appointment = Appointment::factory()->create();
    $shipping = User::factory()->withRole(Role::Shipping)->worksAt(Location::factory()->create())->create();

    $this->actingAs($shipping)
        ->post(route('dashboard.appointments.notes.store', ['uuid' => $appointment->uuid]), ['body' => 'Hello'])
        ->assertNotFound();

    expect(AppointmentNote::query()->count())->toBe(0)
        ->and(RecordHistory::query()->where('event', RecordHistoryEvent::NoteAdded)->count())->toBe(0)
        ->and(UserHistory::query()->count())->toBe(0);
});

it('rejects an invalid note body', function (array $payload, string $message): void {
    $appointment = Appointment::factory()->create();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.appointments.notes.store', ['uuid' => $appointment->uuid]), $payload)
        ->assertSessionHasErrors(['body' => $message]);

    expect(AppointmentNote::query()->count())->toBe(0);
})->with([
    'blank' => [['body' => '   '], 'The body field is required.'],
    'too long' => [['body' => str_repeat('a', 2001)], 'The body field must not be greater than 2000 characters.'],
]);

it('shows the notes newest first with a timeline entry for each', function (): void {
    $staff = User::factory()->withRole(Role::Admin)->create(['name' => 'Dana Lee']);
    $appointment = Appointment::factory()->create();

    Date::setTestNow(now()->subMinute());
    $this->actingAs($staff)->post(route('dashboard.appointments.notes.store', ['uuid' => $appointment->uuid]), ['body' => 'First']);
    Date::setTestNow();
    $this->actingAs($staff)->post(route('dashboard.appointments.notes.store', ['uuid' => $appointment->uuid]), ['body' => 'Second']);

    $this->actingAs($staff)
        ->get(route('dashboard.appointments.show', ['uuid' => $appointment->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('notes', 2)
            ->where('notes.0.body', 'Second')
            ->where('notes.0.author', 'Dana Lee')
            ->where('notes.1.body', 'First')
            ->where('appointment.history.1.description', 'Note added')
            ->where('appointment.history.1.actor', 'Dana Lee'));
});

it('keeps a note after its author account is deleted', function (): void {
    $note = AppointmentNote::factory()->create();
    $note->user->delete();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->get(route('dashboard.appointments.show', ['uuid' => $note->appointment->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('notes.0.body', $note->body)
            ->where('notes.0.author', null));
});
