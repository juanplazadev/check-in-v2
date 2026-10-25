<?php

declare(strict_types=1);

use App\Enums\RecordHistoryEvent;
use App\Enums\Role;
use App\Enums\UserHistoryEvent;
use App\Models\Address;
use App\Models\Location;
use App\Models\RecordHistory;
use App\Models\User;
use App\Models\UserHistory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

/*
 * Admins editing a location's details, one section of the Details page at a
 * time, and the location's History section that logs it.
 */

function detailsAdmin(): User
{
    return User::factory()->withRole(Role::Admin)->create(['name' => 'Ada Admin']);
}

/**
 * Answers the Census geocoder with the given matches.
 *
 * @param  list<array{coordinates: array{x: float, y: float}}>  $matches
 */
function fakeGeocoder(array $matches): void
{
    Http::fake([
        'geocoding.geo.census.gov/geocoder/locations/address*' => Http::response(['result' => ['addressMatches' => $matches]]),
    ]);
}

function editableLocation(): Location
{
    return Location::factory()->create([
        'name' => 'Alpha Dock',
        'abbreviation' => 'ALP',
        'timezone' => 'America/New_York',
        'max_distance_allowed' => 50,
        'address_id' => Address::factory()->create(['street1' => '1 Main St', 'city' => 'Miami', 'state' => 'FL', 'zip_code' => '33101']),
    ]);
}

it('saves a section and logs the changed fields on both trails', function (): void {
    $admin = detailsAdmin();
    $location = editableLocation();

    $this->actingAs($admin)
        ->from(route('dashboard.locations.details', ['uuid' => $location->uuid]))
        ->patch(route('dashboard.locations.details.update', ['uuid' => $location->uuid]), [
            'name' => 'Bravo Dock',
            'abbreviation' => 'brv',
            'timezone' => 'America/New_York',
        ])
        ->assertRedirect(route('dashboard.locations.details', ['uuid' => $location->uuid]))
        ->assertInertiaFlash('toast.message', 'Bravo Dock was updated.');

    $expected = [
        'name' => ['from' => 'Alpha Dock', 'to' => 'Bravo Dock'],
        'abbreviation' => ['from' => 'ALP', 'to' => 'BRV'],
    ];

    $entry = RecordHistory::query()->sole();
    $trail = UserHistory::query()->sole();

    expect($location->refresh()->only('name', 'abbreviation'))->toBe(['name' => 'Bravo Dock', 'abbreviation' => 'BRV'])
        ->and($entry->recordable_type)->toBe('location')
        ->and($entry->recordable_id)->toBe($location->id)
        ->and($entry->event)->toBe(RecordHistoryEvent::DetailsEdited)
        ->and($entry->user_id)->toBe($admin->id)
        ->and($entry->context)->toBe($expected)
        ->and($trail->user_id)->toBe($admin->id)
        ->and($trail->event)->toBe(UserHistoryEvent::LocationEdited)
        ->and($trail->changes)->toBe(['location' => $location->uuid, ...$expected]);
});

it('saves the address and logs its fields prefixed', function (): void {
    fakeGeocoder([['coordinates' => ['x' => -80.18, 'y' => 25.77]]]);
    $location = editableLocation();

    $this->actingAs(detailsAdmin())
        ->patch(route('dashboard.locations.details.update', ['uuid' => $location->uuid]), [
            'phone' => '(305) 555-0100',
            'phone_ext' => '',
            'email' => 'dock@example.com',
            'street1' => '2 Bay Rd',
            'street2' => '',
            'city' => 'Miami',
            'state' => 'fl',
            'zip_code' => '33101',
            'zip_code_4' => '1234',
            'country' => 'us',
        ])
        ->assertSessionHasNoErrors();

    $address = $location->refresh()->address;

    expect($address->street1)->toBe('2 Bay Rd')
        ->and($address->zip_code_4)->toBe('1234')
        ->and($address->latitude)->toBe(25.77)
        ->and($address->longitude)->toBe(-80.18)
        ->and($location->phone)->toBe('(305) 555-0100')
        ->and(RecordHistory::query()->sole()->context)->toHaveKeys(['phone', 'email', 'address.street1', 'address.zip_code_4', 'address.latitude', 'address.longitude'])
        ->not->toHaveKeys(['address.city', 'address.state', 'address.country']);
});

it('refuses an address the geocoder cannot find', function (): void {
    fakeGeocoder([]);
    $location = editableLocation();

    $this->actingAs(detailsAdmin())
        ->patch(route('dashboard.locations.details.update', ['uuid' => $location->uuid]), ['street1' => '0 Nowhere Rd'])
        ->assertSessionHasErrors(['street1' => 'This address could not be found. Please check the street, city, state and zip code.']);

    expect($location->refresh()->address->street1)->toBe('1 Main St')
        ->and(RecordHistory::query()->count())->toBe(0);
});

it('refuses an address change while the geocoder is down', function (): void {
    Http::fake([
        'geocoding.geo.census.gov/geocoder/locations/address*' => Http::response(status: 503),
    ]);
    $location = editableLocation();

    $this->actingAs(detailsAdmin())
        ->patch(route('dashboard.locations.details.update', ['uuid' => $location->uuid]), ['street1' => '2 Bay Rd'])
        ->assertSessionHasErrors(['street1' => 'The address could not be verified right now. Please try again.']);

    expect($location->refresh()->address->street1)->toBe('1 Main St');
});

it('does not geocode when only street2 or details change', function (): void {
    fakeGeocoder([]);
    $location = editableLocation();

    $this->actingAs(detailsAdmin())
        ->patch(route('dashboard.locations.details.update', ['uuid' => $location->uuid]), ['name' => 'Bravo Dock', 'street2' => 'Unit 4'])
        ->assertSessionHasNoErrors();

    Http::assertNothingSent();
    expect($location->refresh()->address->street2)->toBe('Unit 4');
});

it('saves the check-in radius', function (): void {
    $location = editableLocation();

    $this->actingAs(detailsAdmin())
        ->patch(route('dashboard.locations.details.update', ['uuid' => $location->uuid]), ['max_distance_allowed' => 75])
        ->assertSessionHasNoErrors();

    expect($location->refresh()->max_distance_allowed)->toBe(75);
});

it('writes no history when nothing changed', function (): void {
    $location = editableLocation();

    $this->actingAs(detailsAdmin())
        ->patch(route('dashboard.locations.details.update', ['uuid' => $location->uuid]), ['name' => 'Alpha Dock', 'city' => 'Miami'])
        ->assertInertiaFlash('toast.message', 'Nothing changed.');

    expect(RecordHistory::query()->count())->toBe(0)
        ->and(UserHistory::query()->count())->toBe(0);
});

it('only lets admins edit', function (Role $role): void {
    $location = editableLocation();
    $user = User::factory()->withRole($role)->create();
    $user->locations()->attach($location);

    $this->actingAs($user)
        ->patch(route('dashboard.locations.details.update', ['uuid' => $location->uuid]), ['name' => 'Bravo Dock'])
        ->assertForbidden();

    expect($location->refresh()->name)->toBe('Alpha Dock');
})->with([Role::ShippingManager, Role::Shipping, Role::Sales, Role::Driver]);

it('returns not found for an unknown location', function (): void {
    $this->actingAs(detailsAdmin())
        ->patch(route('dashboard.locations.details.update', ['uuid' => (string) Str::uuid()]), ['name' => 'Bravo Dock'])
        ->assertNotFound();
});

it('rejects fields the edit does not cover', function (): void {
    $location = editableLocation();

    $this->actingAs(detailsAdmin())
        ->patch(route('dashboard.locations.details.update', ['uuid' => $location->uuid]), ['name' => 'Bravo Dock', 'is_active' => false])
        ->assertSessionHasErrors('is_active');

    expect($location->refresh()->name)->toBe('Alpha Dock');
});

it('rejects invalid values', function (array $payload, string $field): void {
    $location = editableLocation();

    $this->actingAs(detailsAdmin())
        ->patch(route('dashboard.locations.details.update', ['uuid' => $location->uuid]), $payload)
        ->assertSessionHasErrors($field);

    expect(RecordHistory::query()->count())->toBe(0);
})->with([
    'blank name' => [['name' => ''], 'name'],
    'long abbreviation' => [['abbreviation' => 'ABCDE'], 'abbreviation'],
    'unknown timezone' => [['timezone' => 'Europe/Paris'], 'timezone'],
    'bad email' => [['email' => 'not-an-email'], 'email'],
    'zero radius' => [['max_distance_allowed' => 0], 'max_distance_allowed'],
    'short zip' => [['zip_code' => '331'], 'zip_code'],
    'letters in extension' => [['phone_ext' => 'x12'], 'phone_ext'],
]);

it('refuses an address another location already has', function (): void {
    $location = editableLocation();
    Location::factory()->create([
        'address_id' => Address::factory()->create(['street1' => '9 Dock Ln', 'city' => 'Miami', 'state' => 'FL', 'zip_code' => '33101']),
    ]);

    $this->actingAs(detailsAdmin())
        ->patch(route('dashboard.locations.details.update', ['uuid' => $location->uuid]), ['street1' => '9 Dock Ln'])
        ->assertSessionHasErrors(['street1' => 'Another location already has this address.']);
});

it('sends timezone options only to those who may edit', function (Role $role, bool $hasOptions): void {
    $location = editableLocation();
    $user = User::factory()->withRole($role)->create();
    $user->locations()->attach($location);

    $this->actingAs($user)
        ->get(route('dashboard.locations.details', ['uuid' => $location->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('timezones', fn ($timezones): bool => collect($timezones)->contains('America/Chicago') === $hasOptions));
})->with([
    'admin' => [Role::Admin, true],
    'shipping manager' => [Role::ShippingManager, false],
]);

it("lists the location's history newest first", function (): void {
    $location = editableLocation();
    $admin = detailsAdmin();
    $shipping = User::factory()->withRole(Role::Shipping)->create(['name' => 'Sam']);

    $this->actingAs($admin)->post(route('dashboard.locations.users.store', ['uuid' => $location->uuid]), ['users' => [$shipping->uuid]]);
    $this->travel(1)->minute();
    $this->actingAs($admin)->patch(route('dashboard.locations.details.update', ['uuid' => $location->uuid]), ['max_distance_allowed' => 75]);

    $this->actingAs($admin)
        ->get(route('dashboard.locations.history', ['uuid' => $location->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Dashboard/Locations/History')
            ->has('history.data', 2)
            ->where('history.data.0.description', 'Details edited')
            ->where('history.data.0.actor', 'Ada Admin')
            ->where('history.data.0.changes.0.label', 'Check-in radius (mi)')
            ->where('history.data.0.changes.0.from', '50')
            ->where('history.data.0.changes.0.to', '75')
            ->where('history.data.1.description', 'Sam added to this location'));
});
