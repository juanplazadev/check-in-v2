<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Location;
use App\Models\User;

/*
 * The hours editor's client side: presets, the live summary, the save bar and saving.
 */

it('sets weekday hours with a preset and saves them', function (): void {
    $location = Location::factory()->create(['name' => 'Alpha Dock']);
    $this->actingAs(User::factory()->withRole(Role::ShippingManager)->worksAt($location)->create());

    $page = visit(route('dashboard.locations.checkInHours', ['uuid' => $location->uuid]))
        ->assertSee('Every day open 24 hours');

    $page->click('button:has-text("Weekdays 9-5")')
        ->assertSee('Mon-Fri 9:00 AM - 5:00 PM · Closed weekends')
        ->assertSee('7 days changed')
        ->click('button:has-text("Save hours")')
        ->assertSee('Check-in hours for Alpha Dock saved.')
        ->assertNoJavascriptErrors();

    expect($location->checkinSchedule()->orderBy('day_of_week')->pluck('day_of_week')->all())->toBe([1, 2, 3, 4, 5])
        ->and($location->checkinSchedule()->where('open_time', '09:00:00')->where('close_time', '17:00:00')->count())->toBe(5);
});

it('closes a day live and discards the change', function (): void {
    $location = Location::factory()->create();
    $this->actingAs(User::factory()->withRole(Role::ShippingManager)->worksAt($location)->create());

    $page = visit(route('dashboard.locations.checkInHours', ['uuid' => $location->uuid]));

    $page->click('[aria-label="Sunday open"]')
        ->assertSee('Mon-Sat open 24 hours · Closed Sun')
        ->click('button:has-text("Discard")')
        ->assertSee('Every day open 24 hours')
        ->assertNoJavascriptErrors();

    expect($location->checkinSchedule()->count())->toBe(7);
});

it('picks a closing time from the list and saves it', function (): void {
    $location = Location::factory()->create();
    $this->actingAs(User::factory()->withRole(Role::ShippingManager)->worksAt($location)->create());

    $page = visit(route('dashboard.locations.checkInHours', ['uuid' => $location->uuid]));

    $page->click('button:has-text("Weekdays 9-5")')
        ->click('[aria-label^="Monday closes"]')
        ->click('[role="option"]:has-text("6:00 PM")')
        ->assertSee('Mon 9:00 AM - 6:00 PM · Tue-Fri 9:00 AM - 5:00 PM · Closed weekends')
        ->click('button:has-text("Save hours")')
        ->assertSee('Check-in hours for')
        ->assertNoJavascriptErrors();

    expect($location->checkinSchedule()->where('day_of_week', 1)->sole()->close_time)->toStartWith('18:00');
});

it('takes a typed time', function (): void {
    $location = Location::factory()->create();
    $this->actingAs(User::factory()->withRole(Role::ShippingManager)->worksAt($location)->create());

    $page = visit(route('dashboard.locations.checkInHours', ['uuid' => $location->uuid]));

    $page->click('button:has-text("Weekdays 9-5")')
        ->click('[aria-label^="Monday opens"]')
        ->type('input[placeholder="Type a time, like 9:30a"]', '730a')
        ->assertSee('Press Enter for 7:30 AM')
        ->keys('input[placeholder="Type a time, like 9:30a"]', 'Enter')
        ->assertSee('Mon 7:30 AM - 5:00 PM')
        ->assertNoJavascriptErrors();
});
