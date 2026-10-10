<?php

declare(strict_types=1);

use App\Enums\AppointmentLinkMethod;
use App\Enums\Role;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\User;
use Carbon\CarbonImmutable;

it('records the appointment, when, how and by whom a staff member linked it', function (): void {
    $staff = User::factory()->withRole(Role::Shipping)->create();
    $appointment = Appointment::factory()->create();

    $checkIn = CheckIn::factory()
        ->linkedToAppointment($appointment, AppointmentLinkMethod::Staff, $staff)
        ->create()
        ->fresh();

    expect($checkIn->appointment->is($appointment))->toBeTrue()
        ->and($checkIn->appointment_linked_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($checkIn->appointment_linked_via)->toBe(AppointmentLinkMethod::Staff)
        ->and($checkIn->appointmentLinkedBy->is($staff))->toBeTrue();
});

it('keeps the appointment link but clears the staff reference when that user is deleted', function (): void {
    $staff = User::factory()->withRole(Role::Shipping)->create();
    $appointment = Appointment::factory()->create();
    $checkIn = CheckIn::factory()
        ->linkedToAppointment($appointment, AppointmentLinkMethod::Staff, $staff)
        ->create();

    $staff->delete();

    $checkIn->refresh();

    expect($checkIn->appointment_linked_by)->toBeNull()
        ->and($checkIn->appointment_id)->toBe($appointment->id)
        ->and($checkIn->appointment_linked_via)->toBe(AppointmentLinkMethod::Staff)
        ->and($checkIn->appointment_linked_at)->not->toBeNull();
});
