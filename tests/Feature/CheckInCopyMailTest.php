<?php

declare(strict_types=1);

use App\Mail\AppointmentCopyMail;
use App\Mail\CheckInCopyMail;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\Location;
use Illuminate\Support\Facades\App;

/**
 * Renders the mailables for real rather than through a fake, so the Blade runs
 * and a missing translation key or a bad payload shape surfaces here.
 */
function copyCheckIn(array $attributes = [], array $config = []): CheckIn
{
    $location = Location::factory()->create(['config' => $config]);

    return CheckIn::factory()->atLocation($location)->create([
        'customer' => 'Acme Produce',
        'destination_city' => 'Phoenix',
        'destination_state' => 'Arizona',
        'destination_country' => 'US',
        'truck_name' => 'Truck 42',
        ...$attributes,
    ]);
}

it('renders the check-in details the driver needs', function (): void {
    $checkIn = copyCheckIn();

    $html = new CheckInCopyMail($checkIn)->render();

    // Escaped before comparing: these come from faker, which happily generates
    // company names like "O'Kon Inc", and Blade renders that as O&#039;Kon Inc.
    // Comparing raw made this test fail only when the random name happened to
    // contain an apostrophe.
    expect($html)->toContain($checkIn->reference_number)
        ->and($html)->toContain(e($checkIn->location->name))
        ->and($html)->toContain('Acme Produce')
        ->and($html)->toContain('Phoenix, Arizona, US')
        ->and($html)->toContain('Truck 42');
});

/*
 * The point of the row filter. A location that does not collect truck colour
 * stores null, and a body that rendered every row regardless would show the
 * driver a labelled blank.
 */
it('omits rows for fields the location does not collect', function (): void {
    $checkIn = copyCheckIn(['truck_color' => null, 'empty_weight_lbs' => null]);

    $html = new CheckInCopyMail($checkIn)->render();

    expect($html)->not->toContain('Truck color')
        ->and($html)->not->toContain('Empty weight');
});

it('shows those rows once the location does collect them', function (): void {
    $checkIn = copyCheckIn(
        ['truck_color' => 'White', 'empty_weight_lbs' => 32000],
        ['checkin' => ['show_truck_color' => true, 'show_empty_weight_lbs' => true]],
    );

    $html = new CheckInCopyMail($checkIn)->render();

    expect($html)->toContain('Truck color')
        ->and($html)->toContain('White')
        ->and($html)->toContain('Empty weight')
        ->and($html)->toContain('32000');
});

/*
 * The driver already knows their own number, and this document is forwardable,
 * so the copy must never carry the licence in full.
 */
it('never leaks the full licence number', function (): void {
    $checkIn = copyCheckIn(['drivers_license_number' => 'D12345678']);

    expect(new CheckInCopyMail($checkIn)->render())->not->toContain('D12345678');
});

it('attaches the pdf receipt', function (): void {
    $mail = new CheckInCopyMail(copyCheckIn());
    $mail->render();

    expect($mail->attachments())->toHaveCount(1);
});

it('renders in the locale the mailable was given', function (): void {
    App::setLocale('es');

    $html = new CheckInCopyMail(copyCheckIn())->render();

    expect($html)->toContain('Tu copia');
});

it('renders the booking copy with its own details', function (): void {
    $appointment = Appointment::factory()->create();

    $html = new AppointmentCopyMail($appointment)->render();

    // Escaped for the same reason as above - faker names carry apostrophes.
    expect($html)->toContain($appointment->reference_number)
        ->and($html)->toContain(e($appointment->location->name))
        ->and($html)->toContain(e($appointment->drivers_name));
});
