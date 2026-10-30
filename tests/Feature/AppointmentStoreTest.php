<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Events\AppointmentCreated;
use App\Models\Appointment;
use App\Models\LoadType;
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

it('books the chosen load type and holds the dock for its duration', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    $loadType = LoadType::factory()->for($location)->lasting(90)->create();
    passGate($location);

    post(route('appointment.store', $location->uuid), validPayload(['load_type' => $loadType->uuid]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $appointment = Appointment::query()->sole();

    expect($appointment->load_type_id)->toBe($loadType->id)
        ->and($appointment->ends_at->equalTo($appointment->scheduled_for->addMinutes(90)))->toBeTrue();
});

it('books one slot with no load type at a location that has none', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    passGate($location);

    post(route('appointment.store', $location->uuid), validPayload())
        ->assertSessionHasNoErrors();

    $appointment = Appointment::query()->sole();

    expect($appointment->load_type_id)->toBeNull()
        ->and($appointment->ends_at->equalTo($appointment->scheduled_for->addMinutes($location->appointmentSlotIntervalMinutes())))->toBeTrue();
});

it('requires a load type at a location that has active ones', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    LoadType::factory()->for($location)->create();
    passGate($location);

    post(route('appointment.store', $location->uuid), validPayload())
        ->assertSessionHasErrors(['load_type' => 'Please select a load type.']);

    expect(Appointment::query()->count())->toBe(0);
});

it('rejects a load type that is turned off', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    LoadType::factory()->for($location)->create();
    $turnedOff = LoadType::factory()->for($location)->inactive()->create();
    passGate($location);

    post(route('appointment.store', $location->uuid), validPayload(['load_type' => $turnedOff->uuid]))
        ->assertSessionHasErrors(['load_type' => 'That load type is no longer available. Please choose another.']);

    expect(Appointment::query()->count())->toBe(0);
});

it('rejects a load type from another location', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    LoadType::factory()->for($location)->create();
    $elsewhere = LoadType::factory()->create();
    passGate($location);

    post(route('appointment.store', $location->uuid), validPayload(['load_type' => $elsewhere->uuid]))
        ->assertSessionHasErrors(['load_type' => 'That load type is no longer available. Please choose another.']);

    expect(Appointment::query()->count())->toBe(0);
});

it('rejects a load that would run past closing time', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    $loadType = LoadType::factory()->for($location)->lasting(120)->create();
    passGate($location);

    // The location closes at 23:59:59, so a two-hour load cannot start at 23:00.
    post(route('appointment.store', $location->uuid), validPayload([
        'datetime' => now()->setTime(23, 0)->format('Y-m-d H:i:s'),
        'load_type' => $loadType->uuid,
    ]))->assertSessionHasErrors(['datetime' => 'This load would run past closing time. Please choose an earlier time or another day.']);
});
