<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Support\Str;

use function Pest\Laravel\get;

it('renders the confirmation page for a valid appointment UUID', function (): void {
    $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Scheduled]);
    $location = $appointment->location;

    get(route('appointment.confirmed', $appointment->uuid))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('AppointmentConfirmation')
            ->has('appointment', fn ($prop) => $prop
                ->where('uuid', $appointment->uuid)
                ->where('referenceNumber', $appointment->reference_number)
                ->has('scheduledDate')
                ->has('scheduledTime')
                ->where('loadType', null)
                ->has('driversName')
                ->has('locationName')
                ->has('locationAddress')
                ->has('purchaseOrders')
            )
            ->has('contact', fn ($prop) => $prop
                ->where('phone', $location->phone)
                ->where('email', $location->email)
            )
        );
});

it('returns 404 for a non-existent appointment UUID', function (): void {
    get(route('appointment.confirmed', Str::uuid()->toString()))
        ->assertNotFound();
});
