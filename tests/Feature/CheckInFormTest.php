<?php

declare(strict_types=1);

use App\Auth\RegistrationGate;
use App\CheckIn\CheckInScheduleResolver;
use App\DTOs\CheckInLocationDTO;
use App\Enums\CheckInErpStatus;
use App\Enums\CheckInStatus;
use App\Enums\Role;
use App\Events\CheckInCreated;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\User;
use App\Notifications\CheckInReceived;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

/**
 * @return array{checkInGatePass: array{uuid: string, location: CheckInLocationDTO, fields: array<string, bool>, storedAt: int}, userCoordinates: array{latitude: float, longitude: float, storedAt: int}}
 */
function freshGatePass(Location $location): array
{
    return [
        'checkInGatePass' => [
            'uuid' => $location->uuid,
            'location' => resolve(CheckInScheduleResolver::class)->buildDTO($location, now()),
            'fields' => $location->checkInFormFields(),
            'storedAt' => now()->timestamp,
        ],
        'userCoordinates' => [
            'latitude' => $location->address->latitude,
            'longitude' => $location->address->longitude,
            'storedAt' => now()->timestamp,
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function validCheckInPayload(): array
{
    return [
        'customer' => 'Acme Produce',
        'destination_city' => 'Phoenix',
        'destination_state' => 'Arizona',
        'destination_country' => 'US',
        'loading_instructions' => 'Dock 4, call on arrival.',
        'po_numbers' => ['PO-12345'],
        'truck_name' => 'Truck 42',
        'truck_plate' => 'ABC1234',
        'trailer_plate' => 'XYZ9876',
        'trailer_chute' => 'center-chute',
        'drivers_name' => 'John Driver',
        'drivers_cellphone' => '(555) 123-4567',
        'drivers_license_number' => 'D12345678',
    ];
}

it('redirects to location select when the gate pass is missing', function (): void {
    $location = Location::factory()->create();

    $this->get(route('checkIn.form', $location))
        ->assertRedirect(route('checkIn.selectLocation'))
        ->assertSessionHasErrors('uuid');
});

it('renders the check-in form with the location and field flags', function (): void {
    $location = Location::factory()->checkinAllOptionalFields()->create();

    $response = $this->withSession(freshGatePass($location))
        ->get(route('checkIn.form', $location));

    $response->assertSuccessful();

    $page = $response->viewData('page');

    expect($page['component'])->toBe('CheckInForm')
        ->and($page['props']['location']->id)->toBe($location->uuid)
        ->and($page['props']['fields'])->toBe([
            'showTruckColor' => true,
            'showEmptyWeightLbs' => true,
            'showTruckPlateState' => true,
            'showTruckPlateCountry' => true,
            'showTrailerPlateState' => true,
            'showTrailerPlateCountry' => true,
            'showDriversLicenseState' => true,
            'showDriversLicenseCountry' => true,
            'showDriversLicenseExpirationDate' => true,
        ]);
});

it('exposes a signed-in driver profile so the form can prefill', function (): void {
    $location = Location::factory()->create();
    $user = User::query()->create([
        'name' => 'John Driver',
        'email' => 'john@example.com',
        'password' => 'secret-password',
        'cellphone' => '+12015550123',
    ]);

    $this->actingAs($user)
        ->withSession(freshGatePass($location))
        ->get(route('checkIn.form', $location))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('CheckInForm')
            ->where('auth.user.name', 'John Driver')
            ->where('auth.user.cellphone', '+12015550123'));
});

it('hides optional fields when the location config does not enable them', function (): void {
    $location = Location::factory()->create();

    $response = $this->withSession(freshGatePass($location))
        ->get(route('checkIn.form', $location));

    expect($response->viewData('page')['props']['fields'])->toBe([
        'showTruckColor' => false,
        'showEmptyWeightLbs' => false,
        'showTruckPlateState' => false,
        'showTruckPlateCountry' => false,
        'showTrailerPlateState' => false,
        'showTrailerPlateCountry' => false,
        'showDriversLicenseState' => false,
        'showDriversLicenseCountry' => false,
        'showDriversLicenseExpirationDate' => false,
    ]);
});

it('validates the required check-in fields', function (): void {
    $location = Location::factory()->create();

    $this->withSession(freshGatePass($location))
        ->from(route('checkIn.form', $location))
        ->post(route('checkIn.store', $location), [])
        ->assertRedirect(route('checkIn.form', $location))
        ->assertSessionHasErrors([
            'customer',
            'destination_city',
            'destination_state',
            'destination_country',
            'po_numbers',
            'truck_name',
            'truck_plate',
            'trailer_plate',
            'drivers_name',
            'drivers_cellphone',
            'drivers_license_number',
        ]);
});

it('accepts a valid submission when optional fields are hidden', function (): void {
    $location = Location::factory()->create();

    $response = $this->withSession(freshGatePass($location))
        ->from(route('checkIn.form', $location))
        ->post(route('checkIn.store', $location), validCheckInPayload());

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('checkIn.confirmed', CheckIn::query()->sole()));

    expect(CheckIn::query()->sole()->drivers_email)->toBeNull();
});

it('prefills the check-in with the signed-in driver saved license', function (): void {
    $location = Location::factory()->create();
    $user = User::query()->create([
        'name' => 'John Driver',
        'email' => 'john@example.com',
        'password' => 'secret-password',
        'drivers_license_number' => 'D1234567',
        'drivers_license_state' => 'Sonora',
        'drivers_license_country' => 'MX',
        'drivers_license_expiration_date' => '2030-05-01',
    ]);

    $this->actingAs($user)
        ->withSession(freshGatePass($location))
        ->get(route('checkIn.form', $location))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('CheckInForm')
            ->where('driverLicense.number', 'D1234567')
            ->where('driverLicense.state', 'Sonora')
            ->where('driverLicense.country', 'MX')
            ->where('driverLicense.expirationDate', '2030-05-01'));
});

it('sends a null driver license for guests on the check-in form', function (): void {
    $location = Location::factory()->create();

    $this->withSession(freshGatePass($location))
        ->get(route('checkIn.form', $location))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('CheckInForm')
            ->where('driverLicense', null));
});

it('captures the signed-in driver on the check-in', function (): void {
    $location = Location::factory()->create();
    $user = User::query()->create([
        'name' => 'John Driver',
        'email' => 'john@example.com',
        'password' => 'secret-password',
    ]);

    $this->actingAs($user)
        ->withSession(freshGatePass($location))
        ->from(route('checkIn.form', $location))
        ->post(route('checkIn.store', $location), validCheckInPayload())
        ->assertSessionHasNoErrors();

    $checkIn = CheckIn::query()->sole();

    expect($checkIn->user_id)->toBe($user->id)
        ->and($checkIn->drivers_email)->toBe('john@example.com');
});

it('accepts a submission that omits the optional trailer chute', function (): void {
    $location = Location::factory()->create();

    $payload = validCheckInPayload();
    unset($payload['trailer_chute']);

    $response = $this->withSession(freshGatePass($location))
        ->from(route('checkIn.form', $location))
        ->post(route('checkIn.store', $location), $payload);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('checkIn.confirmed', CheckIn::query()->sole()));
});

it('rejects a trailer chute value outside the allowed set', function (): void {
    $location = Location::factory()->create();

    $this->withSession(freshGatePass($location))
        ->from(route('checkIn.form', $location))
        ->post(route('checkIn.store', $location), [
            ...validCheckInPayload(),
            'trailer_chute' => 'top-chute',
        ])
        ->assertSessionHasErrors(['trailer_chute']);
});

it('requires the optional fields when the location config shows them', function (): void {
    $location = Location::factory()->checkinAllOptionalFields()->create();

    $this->withSession(freshGatePass($location))
        ->from(route('checkIn.form', $location))
        ->post(route('checkIn.store', $location), validCheckInPayload())
        ->assertRedirect(route('checkIn.form', $location))
        ->assertSessionHasErrors([
            'truck_color',
            'truck_plate_state',
            'truck_plate_country',
            'trailer_plate_state',
            'trailer_plate_country',
            'empty_weight_lbs',
            'drivers_license_state',
            'drivers_license_country',
            'drivers_license_expiration_date',
        ]);
});

it('accepts a valid submission including the config-driven fields', function (): void {
    $location = Location::factory()->checkinAllOptionalFields()->create();

    $payload = [
        ...validCheckInPayload(),
        'truck_color' => 'white',
        'truck_plate_state' => 'Arizona',
        'truck_plate_country' => 'us',
        'trailer_plate_state' => 'Sonora',
        'trailer_plate_country' => 'mx',
        'empty_weight_lbs' => 15000,
        'drivers_license_state' => 'Arizona',
        'drivers_license_country' => 'US',
        'drivers_license_expiration_date' => now()->addYear()->format('Y-m-d'),
    ];

    $response = $this->withSession(freshGatePass($location))
        ->from(route('checkIn.form', $location))
        ->post(route('checkIn.store', $location), $payload);

    $checkIn = CheckIn::query()->sole();

    $response->assertSessionHasNoErrors()
        ->assertSessionMissing('checkInGatePass')
        ->assertRedirect(route('checkIn.confirmed', $checkIn));

    expect($checkIn)
        ->location_id->toBe($location->id)
        ->customer->toBe('Acme Produce')
        ->truck_color->toBe('white')
        ->empty_weight_lbs->toEqual(15000)
        ->status->toBe(CheckInStatus::Pending)
        ->erp_status->toBe(CheckInErpStatus::Pending)
        ->reference_number->toHaveLength(8);

    expect($checkIn->purchaseOrders()->pluck('number')->all())->toBe(['PO-12345']);
});

it('opens the registration window after a successful check-in', function (): void {
    $location = Location::factory()->create();

    $response = $this->withSession(freshGatePass($location))
        ->from(route('checkIn.form', $location))
        ->post(route('checkIn.store', $location), validCheckInPayload());

    $checkIn = CheckIn::query()->sole();

    $response->assertSessionHas(RegistrationGate::SESSION_KEY)
        ->assertSessionHas(RegistrationGate::CELLPHONE_KEY, '+15551234567')
        ->assertSessionHas(RegistrationGate::NAME_KEY, 'John Driver')
        ->assertSessionHas(RegistrationGate::CLAIM_TYPE_KEY, 'check_in')
        ->assertSessionHas(RegistrationGate::CLAIM_ID_KEY, $checkIn->id);
});

it('rejects an expired drivers license', function (): void {
    $location = Location::factory()->checkinAllOptionalFields()->create();

    $payload = [
        ...validCheckInPayload(),
        'truck_color' => 'white',
        'truck_plate_state' => 'Arizona',
        'truck_plate_country' => 'US',
        'trailer_plate_state' => 'Sonora',
        'trailer_plate_country' => 'MX',
        'empty_weight_lbs' => 15000,
        'drivers_license_state' => 'Arizona',
        'drivers_license_country' => 'US',
        'drivers_license_expiration_date' => now()->subDay()->format('Y-m-d'),
    ];

    $this->withSession(freshGatePass($location))
        ->from(route('checkIn.form', $location))
        ->post(route('checkIn.store', $location), $payload)
        ->assertSessionHasErrors([
            'drivers_license_expiration_date' => __('messages.checkInForm.licenseExpired'),
        ]);
});

it('rejects a drivers license expiring more than 60 years out', function (): void {
    $location = Location::factory()->checkinAllOptionalFields()->create();

    $payload = [
        ...validCheckInPayload(),
        'truck_color' => 'white',
        'truck_plate_state' => 'Arizona',
        'truck_plate_country' => 'US',
        'trailer_plate_state' => 'Sonora',
        'trailer_plate_country' => 'MX',
        'empty_weight_lbs' => 15000,
        'drivers_license_state' => 'Arizona',
        'drivers_license_country' => 'US',
        'drivers_license_expiration_date' => now()->addYears(61)->format('Y-m-d'),
    ];

    $this->withSession(freshGatePass($location))
        ->from(route('checkIn.form', $location))
        ->post(route('checkIn.store', $location), $payload)
        ->assertSessionHasErrors([
            'drivers_license_expiration_date' => __('messages.checkInForm.licenseExpirationTooFar'),
        ]);
});

it('rejects the submission when the gate pass has expired', function (): void {
    $location = Location::factory()->create();

    $this->withSession([
        'checkInGatePass' => [
            'uuid' => $location->uuid,
            'storedAt' => now()->subSeconds((int) config('app.checkin_gate_pass_ttl') + 1)->timestamp,
        ],
    ])
        ->post(route('checkIn.store', $location), validCheckInPayload())
        ->assertRedirect(route('checkIn.selectLocation'))
        ->assertSessionHasErrors('uuid');
});

it('redirects the submission to location select when the coordinates have expired', function (): void {
    $location = Location::factory()->create();

    $session = freshGatePass($location);
    $session['userCoordinates']['storedAt'] = now()
        ->subSeconds((int) config('app.user_coordinates_session_ttl') + 1)
        ->timestamp;

    $this->withSession($session)
        ->post(route('checkIn.store', $location), validCheckInPayload())
        ->assertRedirect(route('checkIn.selectLocation'))
        ->assertSessionHasErrors('userCoords');
});

it('redirects the form to location select when the coordinates are missing', function (): void {
    $location = Location::factory()->create();

    $session = freshGatePass($location);
    unset($session['userCoordinates']);

    $this->withSession($session)
        ->get(route('checkIn.form', $location))
        ->assertRedirect(route('checkIn.selectLocation'))
        ->assertSessionHasErrors('userCoords');
});

it('broadcasts a submitted check-in to the dashboard', function (): void {
    $location = Location::factory()->create();
    Event::fake([CheckInCreated::class]);

    $this->withSession(freshGatePass($location))
        ->post(route('checkIn.store', $location), validCheckInPayload())
        ->assertSessionHasNoErrors();

    Event::assertDispatchedTimes(CheckInCreated::class, 1);
    Event::assertDispatched(
        CheckInCreated::class,
        fn (CheckInCreated $event): bool => $event->checkIn->is(CheckIn::query()->sole())
            && $event->broadcastWith()['checkIn']['purchaseOrders'] === ['PO-12345'],
    );
});

it('notifies every verified staff member who works the location of a submitted check-in, and nobody else', function (): void {
    $location = Location::factory()->create();
    $staff = collect(Role::staff())->map(fn (Role $role): User => User::factory()->withRole($role)->worksAt($location)->create());
    $shippingElsewhere = User::factory()->withRole(Role::Shipping)->worksAt(Location::factory()->create())->create();
    $driver = User::factory()->withRole(Role::Driver)->create();
    $unverifiedStaff = User::factory()->withRole(Role::Shipping)->unverified()->create();
    Notification::fake();

    $this->withSession(freshGatePass($location))
        ->post(route('checkIn.store', $location), validCheckInPayload())
        ->assertSessionHasNoErrors();

    $checkIn = CheckIn::query()->sole();

    Notification::assertSentTo(
        $staff,
        CheckInReceived::class,
        fn (CheckInReceived $notification, array $channels): bool => $notification->checkIn->is($checkIn)
            && $channels === ['database', 'broadcast'],
    );
    Notification::assertNotSentTo([$driver, $unverifiedStaff, $shippingElsewhere], CheckInReceived::class);
});
