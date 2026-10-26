<?php

declare(strict_types=1);

use App\Enums\RecordHistoryEvent;
use App\Enums\Role;
use App\Enums\UserHistoryEvent;
use App\Models\Location;
use App\Models\RecordHistory;
use App\Models\User;
use App\Models\UserHistory;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

/*
 * Admins editing a location's check-in form fields and check-in notification
 * emails, one section of its check-in form & notifications page at a time.
 */

function checkInOptionsAdmin(): User
{
    return User::factory()->withRole(Role::Admin)->create(['name' => 'Ada Admin']);
}

function checkInOptionsLocation(): Location
{
    return Location::factory()->create([
        'name' => 'Alpha Dock',
        'config' => [
            'checkin' => [
                'email_addresses' => ['dock@example.com'],
                'show_truck_color' => false,
                'show_empty_weight_lbs' => true,
            ],
            'appointment' => ['email_addresses' => ['booking@example.com'], 'max_booking_days_ahead' => 14],
        ],
    ]);
}

it('saves the form fields and logs each changed one on both trails', function (): void {
    $location = checkInOptionsLocation();
    $admin = checkInOptionsAdmin();

    $this->actingAs($admin)
        ->from(route('dashboard.locations.checkInOptions', ['uuid' => $location->uuid]))
        ->patch(route('dashboard.locations.checkInOptions.update', ['uuid' => $location->uuid]), [
            'show_truck_color' => true,
            'show_empty_weight_lbs' => true,
            'show_drivers_license_state' => true,
        ])
        ->assertRedirect(route('dashboard.locations.checkInOptions', ['uuid' => $location->uuid]))
        ->assertInertiaFlash('toast.message', 'Check-in settings for Alpha Dock saved.');

    $location->refresh();
    $history = $location->history()->sole();
    $trail = UserHistory::query()->sole();
    $expected = [
        'checkin.show_truck_color' => ['from' => false, 'to' => true],
        'checkin.show_drivers_license_state' => ['from' => false, 'to' => true],
    ];

    expect($location->checkInFormFields())->toMatchArray([
        'showTruckColor' => true,
        'showEmptyWeightLbs' => true,
        'showDriversLicenseState' => true,
        'showTrailerPlateState' => false,
    ])
        // The rest of the config is kept.
        ->and($location->checkInEmailAddresses())->toBe(['dock@example.com'])
        ->and($location->config['appointment'])->toBe(['email_addresses' => ['booking@example.com'], 'max_booking_days_ahead' => 14])
        ->and($history->event)->toBe(RecordHistoryEvent::CheckInOptionsEdited)
        ->and($history->user_id)->toBe($admin->id)
        ->and($history->context)->toBe($expected)
        ->and($trail->event)->toBe(UserHistoryEvent::LocationCheckInOptionsEdited)
        ->and($trail->changes)->toBe(['location' => $location->uuid, ...$expected]);
});

it('saves the notification emails trimmed and lowercased', function (): void {
    $location = checkInOptionsLocation();

    $this->actingAs(checkInOptionsAdmin())
        ->patch(route('dashboard.locations.checkInOptions.update', ['uuid' => $location->uuid]), [
            'email_addresses' => ['dock@example.com', '  Yard.Lead@Example.com '],
        ])
        ->assertSessionHasNoErrors();

    expect($location->refresh()->checkInEmailAddresses())->toBe(['dock@example.com', 'yard.lead@example.com'])
        ->and($location->checkInFormFields()['showEmptyWeightLbs'])->toBeTrue()
        ->and($location->history()->sole()->context)->toBe([
            'checkin.email_addresses' => ['from' => ['dock@example.com'], 'to' => ['dock@example.com', 'yard.lead@example.com']],
        ]);
});

it('clears the notification emails with an empty list', function (): void {
    $location = checkInOptionsLocation();

    $this->actingAs(checkInOptionsAdmin())
        ->patch(route('dashboard.locations.checkInOptions.update', ['uuid' => $location->uuid]), ['email_addresses' => []])
        ->assertSessionHasNoErrors();

    expect($location->refresh()->checkInEmailAddresses())->toBe([]);
});

it('writes nothing when nothing changed', function (array $payload): void {
    $location = checkInOptionsLocation();

    $this->actingAs(checkInOptionsAdmin())
        ->patch(route('dashboard.locations.checkInOptions.update', ['uuid' => $location->uuid]), $payload)
        ->assertInertiaFlash('toast.message', 'No changes to save.');

    expect(RecordHistory::query()->count())->toBe(0)
        ->and(UserHistory::query()->count())->toBe(0);
})->with([
    'same fields' => [['show_truck_color' => false, 'show_empty_weight_lbs' => true]],
    'same emails in another case' => [['email_addresses' => ['Dock@Example.com']]],
]);

it('rejects invalid emails', function (array $emails, string $field, string $message): void {
    $location = checkInOptionsLocation();

    $this->actingAs(checkInOptionsAdmin())
        ->patch(route('dashboard.locations.checkInOptions.update', ['uuid' => $location->uuid]), ['email_addresses' => $emails])
        ->assertSessionHasErrors([$field => $message]);

    expect($location->refresh()->checkInEmailAddresses())->toBe(['dock@example.com']);
})->with([
    'not an email' => [['dock@example.com', 'not-an-email'], 'email_addresses.1', 'Enter a valid email address.'],
    'blank row' => [[''], 'email_addresses.0', 'Enter an email address or remove this row.'],
    'duplicate in another case' => [['dock@example.com', 'DOCK@example.com'], 'email_addresses.1', 'This email address is already in the list.'],
    'too many' => [array_map(fn (int $i): string => "dock{$i}@example.com", range(1, 11)), 'email_addresses', 'Add at most 10 email addresses.'],
]);

it('rejects fields the page does not cover', function (): void {
    $location = checkInOptionsLocation();

    $this->actingAs(checkInOptionsAdmin())
        ->patch(route('dashboard.locations.checkInOptions.update', ['uuid' => $location->uuid]), [
            'show_truck_color' => true,
            // The radius is edited on the Details page.
            'max_distance_allowed' => 5,
        ])
        ->assertSessionHasErrors('max_distance_allowed');

    expect($location->refresh()->checkInFormFields()['showTruckColor'])->toBeFalse();
});

it('forbids staff without edit check-in options', function (): void {
    $location = checkInOptionsLocation();
    $manager = User::factory()->withRole(Role::ShippingManager)->worksAt($location)->create();

    $this->actingAs($manager)
        ->patch(route('dashboard.locations.checkInOptions.update', ['uuid' => $location->uuid]), ['show_truck_color' => true])
        ->assertForbidden();

    expect($location->refresh()->checkInFormFields()['showTruckColor'])->toBeFalse();
});

it('returns not found for an unknown location', function (): void {
    $this->actingAs(checkInOptionsAdmin())
        ->patch(route('dashboard.locations.checkInOptions.update', ['uuid' => (string) Str::uuid()]), ['show_truck_color' => true])
        ->assertNotFound();
});

it('shows the edit on the location history', function (): void {
    $location = checkInOptionsLocation();
    $admin = checkInOptionsAdmin();

    $this->actingAs($admin)->patch(route('dashboard.locations.checkInOptions.update', ['uuid' => $location->uuid]), [
        'show_truck_color' => true,
        'show_empty_weight_lbs' => false,
    ]);
    $this->travel(1)->minute();
    $this->actingAs($admin)->patch(route('dashboard.locations.checkInOptions.update', ['uuid' => $location->uuid]), [
        'email_addresses' => [],
    ]);

    $this->actingAs($admin)
        ->get(route('dashboard.locations.history', ['uuid' => $location->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('history.data.0.description', 'Check-in form & notifications edited')
            ->where('history.data.0.changes.0.label', 'Notification emails')
            ->where('history.data.0.changes.0.from', 'dock@example.com')
            ->where('history.data.0.changes.0.to', null)
            ->where('history.data.1.actor', 'Ada Admin')
            ->where('history.data.1.changes.0.label', 'Truck color field')
            ->where('history.data.1.changes.0.from', 'Hidden')
            ->where('history.data.1.changes.0.to', 'Shown')
            ->where('history.data.1.changes.1.label', 'Empty weight field')
            ->where('history.data.1.changes.1.to', 'Hidden'));
});
