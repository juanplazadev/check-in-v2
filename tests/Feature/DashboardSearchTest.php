<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\User;
use App\Queries\DashboardSearch;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/*
 * The dashboard header search. Location scoping uses the same visibleTo()
 * pinned in DashboardLocationScopeTest; these cases prove search applies it.
 */

function searchAs(User $user, string $term): TestResponse
{
    return test()->actingAs($user)->getJson(route('dashboard.search', ['q' => $term]));
}

function searchAdmin(): User
{
    return User::factory()->withRole(Role::Admin)->create();
}

it('finds a check-in by each searchable field and says which one matched', function (array $attributes, string $term, ?array $match, ?string $purchaseOrder = null): void {
    $checkIn = CheckIn::factory()->create($attributes);
    $purchaseOrder && $checkIn->purchaseOrders()->create(['number' => $purchaseOrder]);
    CheckIn::factory()->create();

    searchAs(searchAdmin(), $term)
        ->assertOk()
        ->assertJsonCount(1, 'checkIns')
        ->assertJsonPath('checkIns.0.uuid', $checkIn->uuid)
        ->assertJsonPath('checkIns.0.match', $match);
})->with([
    // The row already shows the reference and name, so no separate match.
    'reference' => [['reference_number' => 'ZQ7K2M9X'], 'q7k2m', null],
    'driver name' => [['drivers_name' => 'Quintessa Vbrook'], 'vbrook', null],
    'email' => [['drivers_email' => 'quintessa@haulers.test'], 'QUINTESSA@HAUL', ['field' => 'email', 'value' => 'quintessa@haulers.test']],
    'formatted phone' => [['drivers_cellphone' => '+17875550142'], '(787) 555-0142', ['field' => 'cellphone', 'value' => '+17875550142']],
    'truck plate' => [['truck_plate' => 'XQZ9081'], 'qz908', ['field' => 'truckPlate', 'value' => 'XQZ9081']],
    'trailer plate' => [['trailer_plate' => 'TRQ4471'], 'rq447', ['field' => 'trailerPlate', 'value' => 'TRQ4471']],
    'customer' => [['customer' => 'Zephyrine Produce'], 'zephyrine', ['field' => 'customer', 'value' => 'Zephyrine Produce']],
    'license, last four only' => [['drivers_license_number' => 'D1234567'], 'd-123 4567', ['field' => 'license', 'value' => '4567']],
    'purchase order' => [[], 'po-88213', ['field' => 'purchaseOrder', 'value' => 'PO-88213'], 'PO-88213'],
]);

it('matches a license only in full', function (): void {
    CheckIn::factory()->create(['drivers_license_number' => 'D1234567']);

    searchAs(searchAdmin(), 'D12345')
        ->assertOk()
        ->assertJsonCount(0, 'checkIns');
});

it('does not match a plate search against phone digits', function (): void {
    CheckIn::factory()->create(['drivers_cellphone' => '+17875559081']);

    searchAs(searchAdmin(), 'XQZ9081')
        ->assertOk()
        ->assertJsonCount(0, 'checkIns');
});

it('finds an appointment by each searchable field and says which one matched', function (Closure $makeAppointment, string $term, ?array $match): void {
    $appointment = $makeAppointment();
    Appointment::factory()->create();

    searchAs(searchAdmin(), $term)
        ->assertOk()
        ->assertJsonCount(1, 'appointments')
        ->assertJsonPath('appointments.0.uuid', $appointment->uuid)
        ->assertJsonPath('appointments.0.match', $match);
})->with([
    'reference' => [fn (): Appointment => Appointment::factory()->create(['reference_number' => 'ZQ7K2M9X']), 'q7k2m', null],
    'driver name' => [fn (): Appointment => Appointment::factory()->create(['drivers_name' => 'Quintessa Vbrook']), 'vbrook', null],
    'formatted phone' => [fn (): Appointment => Appointment::factory()->create(['drivers_cellphone' => '+17875550142']), '787-555-0142', ['field' => 'cellphone', 'value' => '+17875550142']],
    'booking account email' => [fn (): Appointment => Appointment::factory()->create([
        'user_id' => User::factory()->create(['email' => 'quintessa@haulers.test'])->id,
    ]), 'quintessa@haul', ['field' => 'email', 'value' => 'quintessa@haulers.test']],
    'purchase order' => [function (): Appointment {
        $appointment = Appointment::factory()->create();
        $appointment->purchaseOrders()->create(['number' => 'PO-88213']);

        return $appointment;
    }, 'PO-88213', ['field' => 'purchaseOrder', 'value' => 'PO-88213']],
]);

it('treats LIKE wildcards in the term as literal characters', function (string $term): void {
    CheckIn::factory()->create();
    Appointment::factory()->create();

    searchAs(searchAdmin(), $term)
        ->assertOk()
        ->assertJsonCount(0, 'checkIns')
        ->assertJsonCount(0, 'appointments')
        ->assertJsonCount(0, 'users');
})->with(['%%', '__', '\\%']);

it('still finds a value that contains a wildcard character', function (): void {
    $checkIn = CheckIn::factory()->create();
    $checkIn->purchaseOrders()->create(['number' => 'PO_100%']);

    searchAs(searchAdmin(), 'O_100%')
        ->assertOk()
        ->assertJsonPath('checkIns.0.uuid', $checkIn->uuid);
});

it('stores a keyed hash of the license, never the license itself', function (): void {
    $checkIn = CheckIn::factory()->create(['drivers_license_number' => 'D1234567']);

    $hash = DB::table('check_ins')->where('id', $checkIn->id)->value('drivers_license_hash');

    expect($hash)->toHaveLength(64)->not->toContain('D1234567')
        ->and($checkIn->toArray())->not->toHaveKey('drivers_license_hash');
});

it('backfills the license hash on existing check-ins', function (): void {
    $checkIn = CheckIn::factory()->create(['drivers_license_number' => 'D1234567']);
    DB::table('check_ins')->update(['drivers_license_hash' => null]);

    $this->artisan('check-ins:rehash-licenses')->assertSuccessful();

    searchAs(searchAdmin(), 'D1234567')
        ->assertJsonPath('checkIns.0.uuid', $checkIn->uuid);
});

it('only searches the locations a scoped user works at', function (): void {
    $mine = Location::factory()->create();
    $here = CheckIn::factory()->atLocation($mine)->create(['drivers_name' => 'Quintessa Here']);
    CheckIn::factory()->create(['drivers_name' => 'Quintessa Elsewhere']);
    $booked = Appointment::factory()->atLocation($mine)->create(['drivers_name' => 'Quintessa Here']);
    Appointment::factory()->create(['drivers_name' => 'Quintessa Elsewhere']);

    searchAs(User::factory()->withRole(Role::Shipping)->worksAt($mine)->create(), 'quintessa')
        ->assertOk()
        ->assertJsonCount(1, 'checkIns')
        ->assertJsonPath('checkIns.0.uuid', $here->uuid)
        ->assertJsonCount(1, 'appointments')
        ->assertJsonPath('appointments.0.uuid', $booked->uuid);
});

it('finds nothing for a scoped user attached to no location', function (): void {
    CheckIn::factory()->create(['drivers_name' => 'Quintessa Vbrook']);
    Appointment::factory()->create(['drivers_name' => 'Quintessa Vbrook']);

    searchAs(User::factory()->withRole(Role::ShippingManager)->create(), 'quintessa')
        ->assertOk()
        ->assertJsonCount(0, 'checkIns')
        ->assertJsonCount(0, 'appointments');
});

it('includes matching users for admins only', function (Role $role, int $expected): void {
    User::factory()->create(['name' => 'Quintessa Vbrook']);

    searchAs(User::factory()->withRole($role)->create(), 'quintessa')
        ->assertOk()
        ->assertJsonCount($expected, 'users');
})->with([
    'admin' => [Role::Admin, 1],
    'sales' => [Role::Sales, 0],
]);

it('finds a user by their full UUID, in any case, for admins only', function (Role $role, int $expected): void {
    $target = User::factory()->create();
    User::factory()->create();

    $response = searchAs(User::factory()->withRole($role)->create(), mb_strtoupper($target->uuid))
        ->assertOk()
        ->assertJsonCount($expected, 'users');

    if ($expected > 0) {
        $response->assertJsonPath('users.0.uuid', $target->uuid);
    }
})->with([
    'admin' => [Role::Admin, 1],
    'sales' => [Role::Sales, 0],
]);

it('finds a check-in and an appointment by their full UUID, in any case', function (): void {
    $checkIn = CheckIn::factory()->create();
    $appointment = Appointment::factory()->create();
    CheckIn::factory()->create();
    Appointment::factory()->create();

    searchAs(searchAdmin(), mb_strtoupper($checkIn->uuid))
        ->assertOk()
        ->assertJsonCount(1, 'checkIns')
        ->assertJsonPath('checkIns.0.match', ['field' => 'uuid', 'value' => $checkIn->uuid]);

    searchAs(searchAdmin(), $appointment->uuid)
        ->assertOk()
        ->assertJsonCount(1, 'appointments')
        ->assertJsonPath('appointments.0.match', ['field' => 'uuid', 'value' => $appointment->uuid]);
});

it('does not match a partial UUID', function (): void {
    $checkIn = CheckIn::factory()->create();

    searchAs(searchAdmin(), mb_substr($checkIn->uuid, 0, 8))
        ->assertOk()
        ->assertJsonCount(0, 'checkIns');
});

it('does not find a check-in by UUID at a location the scoped user cannot see', function (): void {
    $checkIn = CheckIn::factory()->create();

    searchAs(User::factory()->withRole(Role::Shipping)->worksAt(Location::factory()->create())->create(), $checkIn->uuid)
        ->assertOk()
        ->assertJsonCount(0, 'checkIns');
});

it('finds a location by its UUID for admins only', function (Role $role, int $expected): void {
    $location = Location::factory()->create();
    Location::factory()->create();

    searchAs(User::factory()->withRole($role)->create(), $location->uuid)
        ->assertOk()
        ->assertJsonCount($expected, 'locations');
})->with([
    'admin' => [Role::Admin, 1],
    'sales' => [Role::Sales, 0],
]);

it('does not search locations by anything but UUID', function (): void {
    Location::factory()->create(['name' => 'Quintessa Yard']);

    searchAs(searchAdmin(), 'quintessa')
        ->assertOk()
        ->assertJsonCount(0, 'locations');
});

it('caps each type at the per-type limit, newest first', function (): void {
    $this->freezeTime();
    CheckIn::factory()
        ->count(DashboardSearch::PER_TYPE + 1)
        ->sequence(fn ($sequence): array => ['created_at' => now()->subMinutes($sequence->index)])
        ->create(['drivers_name' => 'Quintessa Vbrook']);
    $newest = CheckIn::query()->latest()->first();

    searchAs(searchAdmin(), 'quintessa')
        ->assertJsonCount(DashboardSearch::PER_TYPE, 'checkIns')
        ->assertJsonPath('checkIns.0.uuid', $newest->uuid);
});

it('rejects a term that is too short, has control characters or is not UTF-8', function (string $term): void {
    searchAs(searchAdmin(), $term)
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('q');
})->with([
    'one character once trimmed' => ' a ',
    'null byte' => "ab\0cd",
    'control character' => "ab\x07cd",
    'invalid UTF-8' => "ab\xC3\x28cd",
]);

it('is closed to guests and drivers', function (): void {
    $this->getJson(route('dashboard.search', ['q' => 'quintessa']))->assertUnauthorized();

    searchAs(User::factory()->withRole(Role::Driver)->create(), 'quintessa')->assertForbidden();
});
