<?php

declare(strict_types=1);

use App\Enums\RecordHistoryEvent;
use App\Enums\Role;
use App\Enums\UserHistoryEvent;
use App\Mail\RecordDocumentMail;
use App\Models\Appointment;
use App\Models\Location;
use App\Models\RecordHistory;
use App\Models\User;
use App\Models\UserHistory;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;

/*
 * Emailing an appointment's PDF to the driver from the dashboard: who may do
 * it, where it goes, and the two trails it writes. Appointments store no
 * email, so only a driver with an account can be reached.
 */

it('emails the pdf to the driver account and logs it on the timeline and the staff trail', function (Role $role): void {
    Mail::fake();
    $location = Location::factory()->create();
    $driver = User::factory()->withRole(Role::Driver)->create(['email' => 'driver@example.com']);
    $appointment = Appointment::factory()->atLocation($location)->forUser($driver)->create(['locale' => 'es']);
    $staff = User::factory()->withRole($role)->worksAt($location)->create();

    $this->actingAs($staff)
        ->from(route('dashboard.appointments.show', ['uuid' => $appointment->uuid]))
        ->post(route('dashboard.appointments.pdf.email', ['uuid' => $appointment->uuid]))
        ->assertRedirect(route('dashboard.appointments.show', ['uuid' => $appointment->uuid]));

    Mail::assertQueued(
        RecordDocumentMail::class,
        fn (RecordDocumentMail $mail): bool => $mail->record->is($appointment)
            && $mail->hasTo('driver@example.com')
            && $mail->queue === 'pdf'
            && $mail->locale === 'es',
    );

    $emailed = RecordHistory::query()->where('event', RecordHistoryEvent::PdfEmailed)->sole();
    expect($emailed->recordable_id)->toBe($appointment->id)
        ->and($emailed->user_id)->toBe($staff->id)
        ->and($emailed->channel)->toBe('mail')
        ->and($emailed->context)->toBe(['recipient' => 'driver@example.com', 'status' => 'scheduled']);

    $trail = UserHistory::query()->where('user_id', $staff->id)->sole();
    expect($trail->event)->toBe(UserHistoryEvent::AppointmentPdfEmailed)
        ->and($trail->changes)->toBe(['appointment' => $appointment->uuid, 'recipient' => 'driver@example.com', 'status' => 'scheduled']);
})->with([
    'admin' => [Role::Admin],
    'shipping manager' => [Role::ShippingManager],
    'shipping' => [Role::Shipping],
]);

it('shows on the timeline which status the emailed pdf carried', function (): void {
    Mail::fake();
    $driver = User::factory()->withRole(Role::Driver)->create(['email' => 'driver@example.com']);
    $appointment = Appointment::factory()->forUser($driver)->cancelled()->create();
    $admin = User::factory()->withRole(Role::Admin)->create();

    $this->actingAs($admin)->post(route('dashboard.appointments.pdf.email', ['uuid' => $appointment->uuid]));

    expect(UserHistory::query()->where('user_id', $admin->id)->sole()->changes['status'])->toBe('cancelled');

    $this->actingAs($admin)
        ->get(route('dashboard.appointments.show', ['uuid' => $appointment->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('appointment.history.0.description', 'PDF emailed to driver@example.com (status: canceled)'));
});

it('refuses an appointment whose driver has no account and writes nothing', function (): void {
    Mail::fake();
    $appointment = Appointment::factory()->create();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.appointments.pdf.email', ['uuid' => $appointment->uuid]))
        ->assertSessionHasErrors(['email' => 'This driver has no email on file.']);

    Mail::assertNothingQueued();
    expect(RecordHistory::query()->where('event', RecordHistoryEvent::PdfEmailed)->count())->toBe(0)
        ->and(UserHistory::query()->count())->toBe(0);
});

it('forbids other roles from emailing an appointment pdf', function (Role $role): void {
    Mail::fake();
    $location = Location::factory()->create();
    $appointment = Appointment::factory()->atLocation($location)->forUser(User::factory()->create())->create();

    $this->actingAs(User::factory()->withRole($role)->worksAt($location)->create())
        ->post(route('dashboard.appointments.pdf.email', ['uuid' => $appointment->uuid]))
        ->assertForbidden();

    Mail::assertNothingQueued();
})->with([
    'sales' => [Role::Sales],
    'driver' => [Role::Driver],
]);

it('sends a guest to sign in before emailing an appointment pdf', function (): void {
    $appointment = Appointment::factory()->create();

    $this->post(route('dashboard.appointments.pdf.email', ['uuid' => $appointment->uuid]))
        ->assertRedirect(route('login'));
});

it('returns not found for an appointment at a location the user does not work', function (): void {
    Mail::fake();
    $appointment = Appointment::factory()->forUser(User::factory()->create())->create();
    $shipping = User::factory()->withRole(Role::Shipping)->worksAt(Location::factory()->create())->create();

    $this->actingAs($shipping)
        ->post(route('dashboard.appointments.pdf.email', ['uuid' => $appointment->uuid]))
        ->assertNotFound();

    Mail::assertNothingQueued();
});
