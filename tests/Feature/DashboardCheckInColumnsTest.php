<?php

declare(strict_types=1);

use App\Enums\CheckInColumn;
use App\Enums\Role;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/*
 * Which columns the check-in table offers each staff member, and saving their
 * show/hide choices to their account. Columns backed by an optional form field
 * follow the user's locations; staff who see every location get them all.
 */

function shippingAt(Location ...$locations): User
{
    return User::factory()->withRole(Role::Shipping)->worksAt(...$locations)->create();
}

it('offers every column to staff who see every location, even when no location collects the optional fields', function (): void {
    Location::factory()->create();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->get(route('dashboard.checkIns'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('columns.options', array_map(fn (CheckInColumn $column): string => $column->value, CheckInColumn::cases())));
});

it("offers an optional-field column only when one of the user's locations collects it", function (): void {
    $plain = Location::factory()->create();
    $withColor = Location::factory()->create(['config' => ['checkin' => ['show_truck_color' => true]]]);
    Location::factory()->checkinAllOptionalFields()->create();

    $this->actingAs(shippingAt($plain, $withColor))
        ->get(route('dashboard.checkIns'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('columns.options', fn ($options): bool => in_array('truckColor', $options->all(), true)
                && in_array('driver', $options->all(), true)
                && ! in_array('emptyWeight', $options->all(), true)
                && ! in_array('truckPlateRegion', $options->all(), true)
                && ! in_array('licenseExpiration', $options->all(), true)));
});

it('offers a region column when a location collects either its state or its country', function (string $flag): void {
    $location = Location::factory()->create(['config' => ['checkin' => [$flag => true]]]);

    $this->actingAs(shippingAt($location))
        ->get(route('dashboard.checkIns'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('columns.options', fn ($options): bool => in_array('truckPlateRegion', $options->all(), true)));
})->with(['show_truck_plate_state', 'show_truck_plate_country']);

it('returns saved choices, leaving out columns no longer offered', function (): void {
    $user = shippingAt(Location::factory()->create());
    $user->update(['config' => ['dashboard' => ['check_ins' => ['columns' => ['driver' => false, 'truckColor' => true]]]]]);

    $this->actingAs($user)
        ->get(route('dashboard.checkIns'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('columns.visibility', ['driver' => false]));
});

it('saves column choices to the account, keeping earlier choices and other settings', function (): void {
    $user = User::factory()->withRole(Role::Admin)->create();
    $user->update(['config' => ['other' => 'kept', 'dashboard' => ['check_ins' => ['columns' => ['customer' => false]]]]]);

    $this->actingAs($user)
        ->patchJson(route('dashboard.checkIns.columns'), ['columns' => ['driver' => false, 'truckColor' => true]])
        ->assertNoContent();

    expect($user->refresh()->config)->toBe([
        'other' => 'kept',
        'dashboard' => ['check_ins' => ['columns' => ['customer' => false, 'driver' => false, 'truckColor' => true]]],
    ]);
});

it('rejects column choices that are not offered to the user', function (array $columns, string $error): void {
    $this->actingAs(shippingAt(Location::factory()->create()))
        ->patchJson(route('dashboard.checkIns.columns'), ['columns' => $columns])
        ->assertJsonValidationErrors($error);
})->with([
    'unknown column' => [['bogus' => true], 'columns'],
    'the reference, which is always shown' => [['reference' => false], 'columns'],
    'a field none of their locations collects' => [['truckColor' => true], 'columns'],
    'a non-boolean value' => [['driver' => 'maybe'], 'columns.driver'],
    'no columns' => [[], 'columns'],
]);

it('saves a column order, keeping the saved show/hide choices', function (): void {
    $user = User::factory()->withRole(Role::Admin)->create();
    $user->update(['config' => ['dashboard' => ['check_ins' => ['columns' => ['customer' => false]]]]]);

    $this->actingAs($user)
        ->patchJson(route('dashboard.checkIns.columns'), ['order' => ['status', 'driver']])
        ->assertNoContent();

    expect($user->refresh()->config)->toBe([
        'dashboard' => ['check_ins' => [
            'columns' => ['customer' => false],
            'column_order' => ['status', 'driver'],
        ]],
    ]);
});

it('keeps the saved order when only show/hide choices are sent', function (): void {
    $user = User::factory()->withRole(Role::Admin)->create();
    $user->update(['config' => ['dashboard' => ['check_ins' => ['column_order' => ['status']]]]]);

    $this->actingAs($user)
        ->patchJson(route('dashboard.checkIns.columns'), ['columns' => ['driver' => false]])
        ->assertNoContent();

    expect(data_get($user->refresh()->config, 'dashboard.check_ins.column_order'))->toBe(['status']);
});

it('returns the saved order, dropping columns no longer offered and appending ones never placed', function (): void {
    $user = shippingAt(Location::factory()->create());
    $user->update(['config' => ['dashboard' => ['check_ins' => ['column_order' => ['status', 'truckColor', 'driver']]]]]);

    $this->actingAs($user)
        ->get(route('dashboard.checkIns'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('columns.order', fn ($order): bool => array_slice($order->all(), 0, 3) === ['status', 'driver', 'customer']
                && ! in_array('truckColor', $order->all(), true)
                && ! in_array('reference', $order->all(), true)));
});

it('rejects a column order with columns that are not offered or repeat', function (array $order, string $error): void {
    $this->actingAs(shippingAt(Location::factory()->create()))
        ->patchJson(route('dashboard.checkIns.columns'), ['order' => $order])
        ->assertJsonValidationErrors($error);
})->with([
    'unknown column' => [['bogus'], 'order.0'],
    'the reference, which is always first' => [['reference'], 'order.0'],
    'a field none of their locations collects' => [['truckColor'], 'order.0'],
    'a repeated column' => [['driver', 'driver'], 'order.0'],
    'not a list' => [['a' => 'driver'], 'order'],
]);

it('forbids saving column choices without a staff role', function (): void {
    $this->actingAs(User::factory()->withRole(Role::Driver)->create())
        ->patchJson(route('dashboard.checkIns.columns'), ['columns' => ['driver' => false]])
        ->assertForbidden();
});

it('carries the optional plate and license fields in the check-in record', function (): void {
    $checkIn = CheckIn::factory()->create([
        'truck_plate_country' => 'MX',
        'trailer_plate_country' => 'CA',
        'drivers_license_country' => 'US',
        'drivers_license_expiration_date' => '2028-04-30',
    ]);

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->get(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('checkIn.truck.plateCountry', 'MX')
            ->where('checkIn.trailer.plateCountry', 'CA')
            ->where('checkIn.driver.licenseCountry', 'US')
            ->where('checkIn.driver.licenseExpirationDate', '2028-04-30'));
});
