<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Events\AppointmentCreated;
use App\Models\Appointment;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Notifications\AppointmentReceived;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

function passGate(Location $location): void
{
    post(route('appointment.gate', $location->uuid), ['uuid' => $location->uuid]);
}

function validPayload(array $overrides = []): array
{
    return array_merge([
        'datetime' => now()->addHour()->startOfHour()->format('Y-m-d H:i:s'),
        'po_numbers' => ['SO-00001'],
        'drivers_name' => 'John Doe',
        'drivers_cellphone' => '(201) 555-0123',
    ], $overrides);
}

beforeEach(function (): void {
    Date::setTestNow(Date::today('UTC')->setTime(8, 0));
});

it('creates an appointment with a single PO number', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    passGate($location);

    post(route('appointment.store', $location->uuid), validPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirectToRoute('appointment.confirmed', Appointment::query()->first()->uuid);

    expect(Appointment::query()->count())->toBe(1)
        ->and(PurchaseOrder::query()->count())->toBe(1)
        ->and(PurchaseOrder::query()->first()->number)->toBe('SO-00001');
});

it('creates an appointment with multiple PO numbers', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    passGate($location);

    post(route('appointment.store', $location->uuid), validPayload([
        'po_numbers' => ['SO-00001', 'SO-00002', 'SO-00003'],
    ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $appointment = Appointment::with('purchaseOrders')->first();

    expect($appointment->purchaseOrders)->toHaveCount(3)
        ->and($appointment->purchaseOrders->pluck('number')->sort()->values()->all())
        ->toBe(['SO-00001', 'SO-00002', 'SO-00003']);
});

it('rejects an empty po_numbers array', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    passGate($location);

    post(route('appointment.store', $location->uuid), validPayload([
        'po_numbers' => [],
    ]))->assertSessionHasErrors('po_numbers');
});

it('accepts various valid PO prefixes', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    passGate($location);

    post(route('appointment.store', $location->uuid), validPayload([
        'po_numbers' => ['PO-00100', 'SO-00200', 'PU-00300'],
    ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(Appointment::query()->first()->purchaseOrders)->toHaveCount(3);
});

it('rejects an invalid PO format', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    passGate($location);

    post(route('appointment.store', $location->uuid), validPayload([
        'po_numbers' => ['INVALID'],
    ]))->assertSessionHasErrors('po_numbers.0');
});

it('rejects duplicate PO numbers in the same request', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    passGate($location);

    post(route('appointment.store', $location->uuid), validPayload([
        'po_numbers' => ['SO-00001', 'SO-00001'],
    ]))->assertSessionHasErrors('po_numbers.1');
});

it('redirects when location does not exist', function (): void {
    post(route('appointment.store', Str::uuid()->toString()), validPayload())
        ->assertRedirect(route('appointment.selectLocation'));
});

it('captures the signed-in driver on the appointment', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    $user = User::query()->create([
        'name' => 'John Driver',
        'email' => 'john@example.com',
        'password' => 'secret-password',
    ]);

    actingAs($user);
    passGate($location);

    post(route('appointment.store', $location->uuid), validPayload())
        ->assertSessionHasNoErrors();

    expect(Appointment::query()->first()->user_id)->toBe($user->id);
});

it('persists the locale on appointment creation', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    passGate($location);

    post(route('appointment.store', $location->uuid), validPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(Appointment::query()->first()->locale)->toBe('en');
});

it('rejects a valid +1 phone number from outside the US and PR', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    passGate($location);

    // Valid Canadian number (New Brunswick): correct +1 10-digit format, wrong region.
    post(route('appointment.store', $location->uuid), validPayload([
        'drivers_cellphone' => '(506) 234-5678',
    ]))->assertSessionHasErrors([
        'drivers_cellphone' => __('messages.appointmentForm.cellphoneRegion'),
    ]);
});

it('notifies every verified staff member who works the location of a booked appointment, and nobody else', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    $staff = collect(Role::staff())->map(fn (Role $role): User => User::factory()->withRole($role)->worksAt($location)->create());
    $shippingElsewhere = User::factory()->withRole(Role::Shipping)->worksAt(Location::factory()->create())->create();
    $driver = User::factory()->withRole(Role::Driver)->create();
    $unverifiedStaff = User::factory()->withRole(Role::Shipping)->unverified()->create();
    passGate($location);
    Notification::fake();

    post(route('appointment.store', $location->uuid), validPayload())->assertSessionHasNoErrors();

    $appointment = Appointment::query()->sole();

    Notification::assertSentTo(
        $staff,
        AppointmentReceived::class,
        fn (AppointmentReceived $notification, array $channels): bool => $notification->appointment->is($appointment)
            && $channels === ['database', 'broadcast'],
    );
    Notification::assertNotSentTo([$driver, $unverifiedStaff, $shippingElsewhere], AppointmentReceived::class);
});

it('broadcasts a booked appointment to the live queue', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    passGate($location);
    Event::fake([AppointmentCreated::class]);

    post(route('appointment.store', $location->uuid), validPayload())->assertSessionHasNoErrors();

    Event::assertDispatchedTimes(AppointmentCreated::class, 1);
    Event::assertDispatched(
        AppointmentCreated::class,
        fn (AppointmentCreated $event): bool => $event->appointment->is(Appointment::query()->sole())
            && $event->broadcastWith()['appointment']['purchaseOrders'] === ['SO-00001'],
    );
});
