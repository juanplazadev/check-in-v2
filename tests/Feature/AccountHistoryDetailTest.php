<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\get;

function detailDriver(string $email = 'driver@example.com'): User
{
    return User::query()->create([
        'name' => 'John Driver',
        'email' => $email,
        'password' => 'secret-password',
        'email_verified_at' => now(),
    ])->assignRole(Role::Driver);
}

it('redirects guests away from the detail pages', function (): void {
    $checkIn = CheckIn::factory()->create();

    get(route('account.history.checkIn', $checkIn))->assertRedirect(route('login'));
});

it('renders a check-in the driver owns, with its purchase orders', function (): void {
    $location = Location::factory()->create(['timezone' => 'America/Phoenix']);
    $driver = detailDriver();

    $checkIn = CheckIn::factory()->forUser($driver)->atLocation($location)->completed()->create([
        'customer' => 'Acme Produce',
        'drivers_license_number' => 'D1234567',
    ]);

    $checkIn->purchaseOrders()->save(new PurchaseOrder(['number' => 'PO-12345']));

    $this->actingAs($driver)
        ->get(route('account.history.checkIn', $checkIn))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Account/HistoryCheckIn')
            ->where('checkIn.referenceNumber', $checkIn->reference_number)
            ->where('checkIn.customer', 'Acme Produce')
            ->where('checkIn.status', 'completed')
            ->where('checkIn.purchaseOrders', ['PO-12345']));
});

it('never exposes the full driver license number', function (): void {
    $location = Location::factory()->create();
    $driver = detailDriver();

    $checkIn = CheckIn::factory()->forUser($driver)->atLocation($location)->create([
        'drivers_license_number' => 'D1234567',
    ]);

    $response = $this->actingAs($driver)->get(route('account.history.checkIn', $checkIn));

    $response->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('checkIn.licenseMasked', '••••4567'));

    expect($response->getContent())->not->toContain('D1234567');
});

it('renders an appointment the driver owns', function (): void {
    $location = Location::factory()->create(['timezone' => 'America/Phoenix']);
    $driver = detailDriver();

    $appointment = Appointment::factory()->forUser($driver)->atLocation($location)
        ->scheduledFor(Date::parse('2026-03-12 03:30:00', 'UTC'))->create();

    $this->actingAs($driver)
        ->get(route('account.history.appointment', $appointment))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Account/HistoryAppointment')
            ->where('appointment.referenceNumber', $appointment->reference_number)
            ->where('appointment.date', 'Mar 11, 2026')
            ->where('appointment.time', '8:30 PM MST')
            ->where('appointment.status', 'scheduled'));
});

it('surfaces the cancellation reason on a cancelled appointment', function (): void {
    $location = Location::factory()->create();
    $driver = detailDriver();

    $appointment = Appointment::factory()->forUser($driver)->atLocation($location)
        ->cancelled('Truck breakdown')->create();

    $this->actingAs($driver)
        ->get(route('account.history.appointment', $appointment))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('appointment.status', 'cancelled')
            ->where('appointment.cancelledReason', 'Truck breakdown')
            ->whereNot('appointment.cancelledAt', null));
});

it('returns 404 for a record owned by someone else', function (): void {
    $location = Location::factory()->create();
    $driver = detailDriver();
    $other = detailDriver('other@example.com');

    $checkIn = CheckIn::factory()->forUser($other)->atLocation($location)->create();
    $appointment = Appointment::factory()->forUser($other)->atLocation($location)->create();

    $this->actingAs($driver)
        ->get(route('account.history.checkIn', $checkIn))
        ->assertNotFound();

    $this->actingAs($driver)
        ->get(route('account.history.appointment', $appointment))
        ->assertNotFound();
});

it('returns 404 for an unclaimed or soft deleted record', function (): void {
    $location = Location::factory()->create();
    $driver = detailDriver();

    $unclaimed = CheckIn::factory()->atLocation($location)->create();
    $deleted = CheckIn::factory()->forUser($driver)->atLocation($location)->create();
    $deleted->delete();

    $this->actingAs($driver)
        ->get(route('account.history.checkIn', $unclaimed))
        ->assertNotFound();

    $this->actingAs($driver)
        ->get(route('account.history.checkIn', $deleted))
        ->assertNotFound();
});
