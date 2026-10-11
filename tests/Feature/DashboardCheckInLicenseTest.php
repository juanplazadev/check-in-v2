<?php

declare(strict_types=1);

use App\Enums\RecordHistoryEvent;
use App\Enums\Role;
use App\Enums\UserHistoryEvent;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\RecordHistory;
use App\Models\User;
use App\Models\UserHistory;
use Inertia\Testing\AssertableInertia;

/*
 * Revealing a driver's full license number on the check-in detail page: who
 * may do it, and the two trails every reveal writes.
 */

function licenseViewedRows(): int
{
    return RecordHistory::query()->where('event', RecordHistoryEvent::LicenseViewed)->count();
}

it('reveals the license number and logs it on the timeline and the staff trail', function (Role $role): void {
    $location = Location::factory()->create();
    $checkIn = CheckIn::factory()->atLocation($location)->create(['drivers_license_number' => 'D1234567']);
    $staff = User::factory()->withRole($role)->worksAt($location)->create();

    $this->actingAs($staff)
        ->post(route('dashboard.checkIns.license', ['uuid' => $checkIn->uuid]))
        ->assertOk()
        ->assertExactJson(['number' => 'D1234567'])
        ->assertHeader('Cache-Control', 'no-store, private');

    $entry = RecordHistory::query()->where('event', RecordHistoryEvent::LicenseViewed)->sole();
    expect($entry->recordable_id)->toBe($checkIn->id)
        ->and($entry->user_id)->toBe($staff->id)
        ->and($entry->context)->toBeNull();

    $trail = UserHistory::query()->sole();
    expect($trail->user_id)->toBe($staff->id)
        ->and($trail->event)->toBe(UserHistoryEvent::CheckInLicenseViewed)
        ->and($trail->changes)->toBe(['check_in' => $checkIn->uuid]);
})->with([
    'admin' => [Role::Admin],
    'shipping manager' => [Role::ShippingManager],
    'shipping' => [Role::Shipping],
]);

it('forbids sales and drivers from revealing the license number', function (Role $role): void {
    $checkIn = CheckIn::factory()->create();

    $this->actingAs(User::factory()->withRole($role)->create())
        ->post(route('dashboard.checkIns.license', ['uuid' => $checkIn->uuid]))
        ->assertForbidden();

    expect(licenseViewedRows())->toBe(0)
        ->and(UserHistory::query()->count())->toBe(0);
})->with([
    'sales' => [Role::Sales],
    'driver' => [Role::Driver],
]);

it('returns not found for a check-in at a location the user does not work', function (): void {
    $checkIn = CheckIn::factory()->create();
    $shipping = User::factory()->withRole(Role::Shipping)->worksAt(Location::factory()->create())->create();

    $this->actingAs($shipping)
        ->post(route('dashboard.checkIns.license', ['uuid' => $checkIn->uuid]))
        ->assertNotFound();

    expect(licenseViewedRows())->toBe(0)
        ->and(UserHistory::query()->count())->toBe(0);
});

it('reveals the license number on an archived check-in', function (): void {
    $checkIn = CheckIn::factory()->create(['drivers_license_number' => 'D1234567']);
    $checkIn->delete();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.checkIns.license', ['uuid' => $checkIn->uuid]))
        ->assertOk()
        ->assertExactJson(['number' => 'D1234567']);
});

it('shows the reveal on the timeline with the staff member as actor', function (): void {
    $staff = User::factory()->withRole(Role::Admin)->create(['name' => 'Dana Lee']);
    $checkIn = CheckIn::factory()->create();

    $this->actingAs($staff)->post(route('dashboard.checkIns.license', ['uuid' => $checkIn->uuid]));

    $this->actingAs($staff)
        ->get(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('checkIn.history', fn ($history): bool => collect($history)->contains(
                fn (array $entry): bool => $entry['event'] === 'license-viewed'
                    && $entry['description'] === "Driver's license viewed"
                    && $entry['actor'] === 'Dana Lee',
            )));
});

it('keeps the full license number out of the detail page props', function (): void {
    $checkIn = CheckIn::factory()->create(['drivers_license_number' => 'D1234567']);

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->get(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]))
        ->assertDontSee('D1234567')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('checkIn.driver.licenseLastFour', '4567'));
});
