<?php

declare(strict_types=1);

use App\Mail\AppointmentBooked;
use App\Models\Location;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\post;

/**
 * @param  array<string, mixed>  $config
 */
function createAppointmentLocation(array $config = []): Location
{
    return Location::factory()->appointmentsOnly()->create([
        'config' => $config,
    ]);
}

function bookAppointment(Location $location): void
{
    post(route('appointment.gate', $location->uuid), ['uuid' => $location->uuid]);

    post(route('appointment.store', $location->uuid), [
        'datetime' => now()->addHour()->startOfHour()->format('Y-m-d H:i:s'),
        'po_numbers' => ['SO-00001'],
        'drivers_name' => 'John Doe',
        'drivers_cellphone' => '(201) 555-0123',
    ])->assertSessionHasNoErrors();
}

beforeEach(function (): void {
    Date::setTestNow(Date::today('UTC')->setTime(8, 0));
});

it('queues the shipping department notification to the configured addresses', function (): void {
    Mail::fake();

    $location = createAppointmentLocation([
        'appointment' => [
            'email_addresses' => ['shipping@example.com', 'ops@example.com'],
        ],
    ]);

    bookAppointment($location);

    Mail::assertQueued(
        AppointmentBooked::class,
        fn (AppointmentBooked $mail): bool => $mail->hasTo('shipping@example.com')
            && $mail->hasTo('ops@example.com'),
    );
});

it('does not queue a notification when the location has no configured addresses', function (): void {
    Mail::fake();

    $location = createAppointmentLocation();

    bookAppointment($location);

    Mail::assertNotQueued(AppointmentBooked::class);
});

it('ignores invalid addresses in the location config', function (): void {
    Mail::fake();

    $location = createAppointmentLocation([
        'appointment' => [
            'email_addresses' => ['not-an-email', 'shipping@example.com'],
        ],
    ]);

    bookAppointment($location);

    Mail::assertQueued(
        AppointmentBooked::class,
        fn (AppointmentBooked $mail): bool => $mail->hasTo('shipping@example.com')
            && ! $mail->hasTo('not-an-email'),
    );
});

it('renders the appointment details in the notification', function (): void {
    Mail::fake();

    $location = createAppointmentLocation([
        'appointment' => [
            'email_addresses' => ['shipping@example.com'],
        ],
    ]);

    bookAppointment($location);

    Mail::assertQueued(AppointmentBooked::class, function (AppointmentBooked $mail): bool {
        $html = $mail->render();

        return str_contains($html, $mail->appointment->reference_number)
            && str_contains($html, e($mail->appointment->location->name))
            && str_contains($html, 'SO-00001')
            && str_contains($html, 'John Doe')
            && str_contains($html, '+1 (201) 555-0123');
    });
});
