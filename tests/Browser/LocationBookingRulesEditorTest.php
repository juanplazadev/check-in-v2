<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Location;
use App\Models\User;

/*
 * The booking rules editor's client side: the sliders, the slot interval control and saving.
 */

it('sets the booking rules with the sliders and the interval control', function (): void {
    $location = Location::factory()->create([
        'name' => 'Alpha Dock',
        'config' => ['appointment' => ['max_booking_days_ahead' => 5, 'slot_interval_minutes' => 15, 'min_lead_time_minutes' => 60]],
    ]);
    $this->actingAs(User::factory()->withRole(Role::Admin)->create());

    $page = visit(route('dashboard.locations.appointmentOptions', ['uuid' => $location->uuid]))
        ->assertSee('5 days ahead');

    $page->click('[aria-label="Edit booking rules"]')
        ->keys('[role="slider"][aria-labelledby="appointment_min_lead_time_minutes_label"]', ['ArrowRight', 'ArrowRight'])
        ->assertSee('1 h 30 min')
        ->keys('[role="slider"][aria-labelledby="appointment_max_booking_days_ahead_label"]', ['End'])
        ->click('[role="radio"]:has-text("30 min")')
        ->click('button:has-text("Save")')
        ->assertSee('Appointment settings for Alpha Dock saved.')
        ->assertSee('7 days ahead')
        ->assertNoJavascriptErrors();

    $location->refresh();

    expect($location->appointmentMaxBookingDaysAhead())->toBe(7)
        ->and($location->appointmentSlotIntervalMinutes())->toBe(30)
        ->and($location->appointmentMinLeadTimeMinutes())->toBe(90);
});
