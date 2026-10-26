<?php

declare(strict_types=1);

namespace App\Appointment;

use App\DTOs\LocationAvailabilityDTO;
use App\DTOs\LocationScheduleDTO;
use App\Models\Location;
use Carbon\CarbonInterface;

final readonly class AppointmentAvailabilityResolver
{
    public function __construct(private AppointmentScheduleResolver $scheduleResolver) {}

    public function isAvailableForAppointment(Location $location, CarbonInterface $appointmentDateTime): LocationAvailabilityDTO
    {
        // A pause stops every new booking, whatever day it is for.
        if ($location->isAppointmentPaused(now())) {
            return new LocationAvailabilityDTO(
                allowed: false,
                reason: __('messages.appointmentBooking.pausedWithReason', [
                    'name' => $location->name,
                    'reason' => $location->appointments_paused_reason,
                ]),
            );
        }

        $schedule = $this->resolveSchedule($location, $appointmentDateTime);

        return new LocationAvailabilityDTO(
            allowed: $schedule->isOpen,
            reason: $this->resolveReason($location, $schedule, $appointmentDateTime)
        );
    }

    public function resolveReason(Location $location, LocationScheduleDTO $schedule, CarbonInterface $appointmentDateTime): ?string
    {
        if ($schedule->isOpen) {
            return null;
        }

        // Admin-configured reason takes priority (e.g., "Closed for maintenance")
        if ($schedule->reason) {
            return __('messages.appointmentBooking.closedForAppointmentsOnDateWithReason', [
                'name' => $location->name,
                'date' => $appointmentDateTime->format('m/d/Y'),
                'reason' => $schedule->reason,
            ]);
        }

        // Schedule exception exists but admin didn't provide a reason - generate one

        if ($schedule->isOverrideClosure) {
            return __('messages.appointmentBooking.closedForAppointmentsOnDateWithoutReason', [
                'name' => $location->name,
                'date' => $appointmentDateTime->format('m/d/Y'),
            ]);
        }

        if ($schedule->hasOverride && $schedule->openTime instanceof CarbonInterface && $schedule->closeTime instanceof CarbonInterface) {
            return __('messages.appointmentBooking.outsideSpecialHours', [
                'name' => $location->name,
                'time' => $appointmentDateTime->format('g:i A T'),
                'date' => $appointmentDateTime->format('m/d/Y'),
                'open' => $schedule->openTime->format('g:i A'),
                'close' => $schedule->closeTime->format('g:i A T'),
            ]);
        }

        // No exception - outside regular business hours
        if ($schedule->openTime instanceof CarbonInterface && $schedule->closeTime instanceof CarbonInterface) {
            return __('messages.appointmentBooking.outsideRegularHours', [
                'name' => $location->name,
                'time' => $appointmentDateTime->format('g:i A T'),
                'date' => $appointmentDateTime->format('m/d/Y'),
                'open' => $schedule->openTime->format('g:i A T'),
                'close' => $schedule->closeTime->format('g:i A T'),
            ]);
        }

        // No hours configured for date requested
        return __('messages.appointmentBooking.notAvailableOnDay', [
            'name' => $location->name,
            'date' => $appointmentDateTime->format('m/d/Y'),
        ]);
    }

    private function resolveSchedule(Location $location, CarbonInterface $appointmentDateTime): LocationScheduleDTO
    {
        if (! isset($location->appointmentSchedule, $location->appointmentScheduleOverrides)) {
            return new LocationScheduleDTO(isOpen: false);
        }

        return $this->scheduleResolver->resolveSchedule($location, $appointmentDateTime);
    }
}
