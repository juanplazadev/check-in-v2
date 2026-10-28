<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\BookAppointmentAction;
use App\Appointment\AppointmentConfirmationResolver;
use App\Appointment\AppointmentScheduleResolver;
use App\Appointment\AppointmentSlotCapacity;
use App\Auth\RegistrationGate;
use App\DTOs\AppointmentLocationDTO;
use App\Http\Requests\AppointmentFormRequest;
use App\Http\Requests\AppointmentLocationSelectRequest;
use App\Models\Location;
use App\Models\User;
use App\Queries\AppointmentLocation;
use App\Session\AppointmentSession;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;
use Throwable;

final class AppointmentController extends Controller
{
    public function __construct(private readonly AppointmentSession $session) {}

    public function selectLocation(AppointmentScheduleResolver $resolver, #[CurrentUser] ?User $user = null): Response
    {
        $locations = $resolver->getLocations();

        // Only honour the stored preference if it is still bookable. The resolver's list is already
        // filtered to active, appointments-enabled locations, so membership is the whole check, and a
        // location that has since been retired quietly reads as "none".
        $usualLocationId = $user?->location?->uuid;

        if ($usualLocationId !== null && $locations->doesntContain(fn (AppointmentLocationDTO $location): bool => $location->id === $usualLocationId)) {
            $usualLocationId = null;
        }

        if ($usualLocationId !== null) {
            // Stable in PHP 8, so everything else keeps the resolver's ordering.
            $locations = $locations
                ->sortByDesc(fn (AppointmentLocationDTO $location): bool => $location->id === $usualLocationId)
                ->values();
        }

        return inertia('AppointmentSelectLocation', [
            'locations' => $locations,
            // A separate scalar rather than a flag on the DTO: AppointmentLocationDTO is serialized into
            // the session by gate(), so widening it would break bookings that are mid-flight across a
            // deploy. Being someone's usual location is a fact about the viewer, not about the location.
            'usualLocationId' => $usualLocationId,
        ]);
    }

    public function gate(AppointmentLocationSelectRequest $request, AppointmentScheduleResolver $resolver): RedirectResponse
    {
        $request->validated();

        $location = $request->location;
        assert($location instanceof Location);

        $this->session->setLocation($resolver->buildDTO($location, now()));

        return to_route('appointment.form', $location->uuid ?? '');
    }

    public function form(AppointmentSlotCapacity $slotCapacity): Response
    {
        $locationDTO = $this->session->getLocation();

        return inertia('AppointmentForm', [
            'location' => $locationDTO,
            // Read on every render rather than stored on the session DTO, so a
            // slot that fills while the driver is on the form shows as full on reload.
            'fullSlots' => $this->fullSlots($slotCapacity, $locationDTO),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function store(AppointmentFormRequest $request, BookAppointmentAction $action, RegistrationGate $registrationGate, #[CurrentUser] ?User $user = null): RedirectResponse
    {
        $locationDTO = $this->session->getLocation();
        if (! $locationDTO instanceof AppointmentLocationDTO) {
            return to_route('appointment.selectLocation');
        }

        $validated = $request->validated();

        $appointment = $action->handle($validated, $locationDTO, $user);

        $this->session->forgetLocation();

        // Open the short registration window, carrying the driver's cellphone so
        // a follow-up account is prefilled with it.
        $registrationGate->allow(
            $validated['drivers_cellphone'] ?? null,
            $validated['drivers_name'] ?? null,
        );
        $registrationGate->claim('appointment', $appointment->id);

        return to_route('appointment.confirmed', $appointment->uuid);
    }

    public function confirmed(string $uuid, AppointmentConfirmationResolver $resolver): Response
    {
        return inertia('AppointmentConfirmation', $resolver->resolve($uuid));
    }

    /**
     * @return array<string, list<string>>
     */
    private function fullSlots(AppointmentSlotCapacity $slotCapacity, ?AppointmentLocationDTO $locationDTO): array
    {
        $location = $locationDTO instanceof AppointmentLocationDTO
            ? resolve(AppointmentLocation::class)->execute($locationDTO->id)
            : null;

        if (! $location instanceof Location) {
            return [];
        }

        $now = now();

        return $slotCapacity->fullSlots($location, $now, $now->copy()->addDays($location->appointmentMaxBookingDaysAhead() + 1));
    }
}
