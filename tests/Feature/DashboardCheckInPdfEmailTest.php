<?php

declare(strict_types=1);

use App\Enums\RecordHistoryEvent;
use App\Enums\Role;
use App\Enums\UserHistoryEvent;
use App\Mail\RecordDocumentMail;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\RecordHistory;
use App\Models\User;
use App\Models\UserHistory;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;

/*
 * Emailing a check-in's PDF to the driver from the dashboard: who may do it,
 * where it goes, and the two trails it writes.
 */

it('emails the pdf to the driver and logs it on the timeline and the staff trail', function (Role $role): void {
    Mail::fake();
    $location = Location::factory()->create();
    $checkIn = CheckIn::factory()->atLocation($location)->create([
        'drivers_email' => 'driver@example.com',
        'locale' => 'es',
    ]);
    $staff = User::factory()->withRole($role)->worksAt($location)->create();

    $this->actingAs($staff)
        ->from(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]))
        ->post(route('dashboard.checkIns.pdf.email', ['uuid' => $checkIn->uuid]), ['email' => 'attacker@example.com'])
        ->assertRedirect(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]));

    Mail::assertQueued(
        RecordDocumentMail::class,
        fn (RecordDocumentMail $mail): bool => $mail->record->is($checkIn)
            && $mail->hasTo('driver@example.com')
            && ! $mail->hasTo('attacker@example.com')
            && $mail->queue === 'pdf'
            && $mail->locale === 'es',
    );

    $emailed = RecordHistory::query()->where('event', RecordHistoryEvent::PdfEmailed)->sole();
    expect($emailed->recordable_id)->toBe($checkIn->id)
        ->and($emailed->user_id)->toBe($staff->id)
        ->and($emailed->channel)->toBe('mail')
        ->and($emailed->context)->toBe(['recipient' => 'driver@example.com', 'status' => 'pending']);

    $trail = UserHistory::query()->sole();
    expect($trail->user_id)->toBe($staff->id)
        ->and($trail->event)->toBe(UserHistoryEvent::CheckInPdfEmailed)
        ->and($trail->changes)->toBe(['check_in' => $checkIn->uuid, 'recipient' => 'driver@example.com', 'status' => 'pending']);
})->with([
    'admin' => [Role::Admin],
    'shipping manager' => [Role::ShippingManager],
    'shipping' => [Role::Shipping],
]);

it('shows on the timeline which status the emailed pdf carried', function (): void {
    Mail::fake();
    $checkIn = CheckIn::factory()->completed()->create(['drivers_email' => 'driver@example.com']);
    $admin = User::factory()->withRole(Role::Admin)->create();

    $this->actingAs($admin)->post(route('dashboard.checkIns.pdf.email', ['uuid' => $checkIn->uuid]));

    expect(UserHistory::query()->sole()->changes['status'])->toBe('completed');

    $this->actingAs($admin)
        ->get(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('checkIn.history.0.description', 'PDF emailed to driver@example.com (status: completed)'));
});

it('emails the pdf of an archived check-in', function (): void {
    Mail::fake();
    $checkIn = CheckIn::factory()->completed()->create(['drivers_email' => 'driver@example.com']);
    $checkIn->delete();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.checkIns.pdf.email', ['uuid' => $checkIn->uuid]))
        ->assertSessionHasNoErrors();

    Mail::assertQueued(RecordDocumentMail::class);
});

it('refuses a check-in with no driver email and writes nothing', function (): void {
    Mail::fake();
    $checkIn = CheckIn::factory()->create(['drivers_email' => null]);

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.checkIns.pdf.email', ['uuid' => $checkIn->uuid]))
        ->assertSessionHasErrors(['email' => 'This driver has no email on file.']);

    Mail::assertNothingQueued();
    expect(RecordHistory::query()->where('event', RecordHistoryEvent::PdfEmailed)->count())->toBe(0)
        ->and(UserHistory::query()->count())->toBe(0);
});

it('forbids other roles from emailing a check-in pdf', function (Role $role): void {
    Mail::fake();
    $location = Location::factory()->create();
    $checkIn = CheckIn::factory()->atLocation($location)->create(['drivers_email' => 'driver@example.com']);

    $this->actingAs(User::factory()->withRole($role)->worksAt($location)->create())
        ->post(route('dashboard.checkIns.pdf.email', ['uuid' => $checkIn->uuid]))
        ->assertForbidden();

    Mail::assertNothingQueued();
})->with([
    'sales' => [Role::Sales],
    'driver' => [Role::Driver],
]);

it('sends a guest to sign in before emailing a check-in pdf', function (): void {
    $checkIn = CheckIn::factory()->create();

    $this->post(route('dashboard.checkIns.pdf.email', ['uuid' => $checkIn->uuid]))
        ->assertRedirect(route('login'));
});

it('returns not found for a check-in at a location the user does not work', function (): void {
    Mail::fake();
    $checkIn = CheckIn::factory()->create(['drivers_email' => 'driver@example.com']);
    $shipping = User::factory()->withRole(Role::Shipping)->worksAt(Location::factory()->create())->create();

    $this->actingAs($shipping)
        ->post(route('dashboard.checkIns.pdf.email', ['uuid' => $checkIn->uuid]))
        ->assertNotFound();

    Mail::assertNothingQueued();
});
