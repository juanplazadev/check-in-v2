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
 * Admins editing a location's appointment booking rules and booking
 * notification emails, one section of its booking & notifications page at a
 * time.
 */

function appointmentOptionsAdmin(): User
{
    return User::factory()->withRole(Role::Admin)->create(['name' => 'Ada Admin']);
}

function appointmentOptionsLocation(): Location
{
    return Location::factory()->create([
        'name' => 'Alpha Dock',
        'config' => [
            'appointment' => [
                'email_addresses' => ['booking@example.com'],
                'max_booking_days_ahead' => 5,
                'slot_interval_minutes' => 30,
            ],
            'checkin' => ['email_addresses' => ['dock@example.com'], 'show_truck_color' => true],
        ],
    ]);
}

it('saves the booking rules and logs each changed one on both trails', function (): void {
    $location = appointmentOptionsLocation();
    $admin = appointmentOptionsAdmin();

    $this->actingAs($admin)
        ->from(route('dashboard.locations.appointmentOptions', ['uuid' => $location->uuid]))
        ->patch(route('dashboard.locations.appointmentOptions.update', ['uuid' => $location->uuid]), [
            'max_booking_days_ahead' => 7,
            'slot_interval_minutes' => 30,
            // Unset in config, so it changes from the app default.
            'min_lead_time_minutes' => '120',
        ])
        ->assertRedirect(route('dashboard.locations.appointmentOptions', ['uuid' => $location->uuid]))
        ->assertInertiaFlash('toast.message', 'Appointment settings for Alpha Dock saved.');

    $location->refresh();
    $history = $location->history()->sole();
    $trail = UserHistory::query()->sole();
    $expected = [
        'appointment.max_booking_days_ahead' => ['from' => 5, 'to' => 7],
        'appointment.min_lead_time_minutes' => ['from' => 60, 'to' => 120],
    ];

    expect($location->appointmentMaxBookingDaysAhead())->toBe(7)
        ->and($location->appointmentSlotIntervalMinutes())->toBe(30)
        ->and($location->appointmentMinLeadTimeMinutes())->toBe(120)
        // The rest of the config is kept.
        ->and($location->appointmentEmailAddresses())->toBe(['booking@example.com'])
        ->and($location->config['checkin'])->toBe(['email_addresses' => ['dock@example.com'], 'show_truck_color' => true])
        ->and($history->event)->toBe(RecordHistoryEvent::AppointmentOptionsEdited)
        ->and($history->user_id)->toBe($admin->id)
        ->and($history->context)->toBe($expected)
        ->and($trail->event)->toBe(UserHistoryEvent::LocationAppointmentOptionsEdited)
        ->and($trail->changes)->toBe(['location' => $location->uuid, ...$expected]);
});

it('saves the notification emails trimmed and lowercased', function (): void {
    $location = appointmentOptionsLocation();

    $this->actingAs(appointmentOptionsAdmin())
        ->patch(route('dashboard.locations.appointmentOptions.update', ['uuid' => $location->uuid]), [
            'email_addresses' => ['booking@example.com', '  Yard.Lead@Example.com '],
        ])
        ->assertSessionHasNoErrors();

    expect($location->refresh()->appointmentEmailAddresses())->toBe(['booking@example.com', 'yard.lead@example.com'])
        ->and($location->appointmentMaxBookingDaysAhead())->toBe(5)
        ->and($location->history()->sole()->context)->toBe([
            'appointment.email_addresses' => ['from' => ['booking@example.com'], 'to' => ['booking@example.com', 'yard.lead@example.com']],
        ]);
});

it('writes nothing when nothing changed', function (array $payload): void {
    $location = appointmentOptionsLocation();

    $this->actingAs(appointmentOptionsAdmin())
        ->patch(route('dashboard.locations.appointmentOptions.update', ['uuid' => $location->uuid]), $payload)
        ->assertInertiaFlash('toast.message', 'No changes to save.');

    expect(RecordHistory::query()->count())->toBe(0)
        ->and(UserHistory::query()->count())->toBe(0)
        ->and($location->refresh()->config['appointment'])->not->toHaveKey('buffer_before_close_minutes');
})->with([
    'same rules' => [['max_booking_days_ahead' => 5, 'slot_interval_minutes' => 30]],
    // Saving the default the page shows does not pin it into the config.
    'the default' => [['buffer_before_close_minutes' => 30]],
    'same emails in another case' => [['email_addresses' => ['Booking@Example.com']]],
]);

it('rejects booking rules outside what the location can book with', function (string $field, mixed $value, string $message): void {
    $location = appointmentOptionsLocation();

    $this->actingAs(appointmentOptionsAdmin())
        ->patch(route('dashboard.locations.appointmentOptions.update', ['uuid' => $location->uuid]), [$field => $value])
        ->assertSessionHasErrors([$field => $message]);

    expect(RecordHistory::query()->count())->toBe(0);
})->with([
    'zero days' => ['max_booking_days_ahead', 0, 'The days ahead field must be at least 1.'],
    'past the cap' => ['max_booking_days_ahead', 8, 'The days ahead field must not be greater than 7.'],
    'unlisted interval' => ['slot_interval_minutes', 20, 'Choose one of the listed slot intervals.'],
    'negative lead time' => ['min_lead_time_minutes', -5, 'The minimum lead time field must be at least 0.'],
    'buffer past the cap' => ['buffer_before_close_minutes', 481, 'The buffer before close field must not be greater than 480.'],
    'not a number' => ['min_lead_time_minutes', 'soon', 'The minimum lead time field must be an integer.'],
    'off the slider step' => ['buffer_before_close_minutes', 50, 'The buffer before close must be in steps of 15 minutes.'],
]);

it('rejects invalid emails', function (): void {
    $location = appointmentOptionsLocation();

    $this->actingAs(appointmentOptionsAdmin())
        ->patch(route('dashboard.locations.appointmentOptions.update', ['uuid' => $location->uuid]), [
            'email_addresses' => ['booking@example.com', 'not-an-email'],
        ])
        ->assertSessionHasErrors(['email_addresses.1' => 'Enter a valid email address.']);

    expect($location->refresh()->appointmentEmailAddresses())->toBe(['booking@example.com']);
});

it('rejects fields the page does not cover', function (): void {
    $location = appointmentOptionsLocation();

    $this->actingAs(appointmentOptionsAdmin())
        ->patch(route('dashboard.locations.appointmentOptions.update', ['uuid' => $location->uuid]), [
            'max_booking_days_ahead' => 7,
            'show_truck_color' => false,
        ])
        ->assertSessionHasErrors('show_truck_color');

    expect($location->refresh()->appointmentMaxBookingDaysAhead())->toBe(5);
});

it('forbids staff without edit appointment options', function (): void {
    $location = appointmentOptionsLocation();
    $manager = User::factory()->withRole(Role::ShippingManager)->worksAt($location)->create();

    $this->actingAs($manager)
        ->patch(route('dashboard.locations.appointmentOptions.update', ['uuid' => $location->uuid]), ['max_booking_days_ahead' => 7])
        ->assertForbidden();

    expect($location->refresh()->appointmentMaxBookingDaysAhead())->toBe(5);
});

it('returns not found for an unknown location', function (): void {
    $this->actingAs(appointmentOptionsAdmin())
        ->patch(route('dashboard.locations.appointmentOptions.update', ['uuid' => (string) Str::uuid()]), ['max_booking_days_ahead' => 7])
        ->assertNotFound();
});

it('sends the limits the booking rules may be set to', function (): void {
    $location = appointmentOptionsLocation();

    $this->actingAs(appointmentOptionsAdmin())
        ->get(route('dashboard.locations.appointmentOptions', ['uuid' => $location->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('options.limits', [
                'maxDaysAhead' => 7,
                'slotIntervalMinutes' => [10, 15, 30, 60],
                'maxLeadTimeMinutes' => 480,
                'maxBufferBeforeCloseMinutes' => 480,
                'minuteStep' => 15,
            ]));
});

it('shows the edit on the location history', function (): void {
    $location = appointmentOptionsLocation();
    $admin = appointmentOptionsAdmin();

    $this->actingAs($admin)->patch(route('dashboard.locations.appointmentOptions.update', ['uuid' => $location->uuid]), [
        'slot_interval_minutes' => 60,
    ]);
    $this->travel(1)->minute();
    $this->actingAs($admin)->patch(route('dashboard.locations.appointmentOptions.update', ['uuid' => $location->uuid]), [
        'email_addresses' => [],
    ]);

    $this->actingAs($admin)
        ->get(route('dashboard.locations.history', ['uuid' => $location->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('history.data.0.description', 'Appointment booking & notifications edited')
            ->where('history.data.0.changes.0.label', 'Notification emails')
            ->where('history.data.0.changes.0.from', 'booking@example.com')
            ->where('history.data.0.changes.0.to', null)
            ->where('history.data.1.actor', 'Ada Admin')
            ->where('history.data.1.changes.0.label', 'Slot interval (min)')
            ->where('history.data.1.changes.0.from', '30')
            ->where('history.data.1.changes.0.to', '60'));
});
