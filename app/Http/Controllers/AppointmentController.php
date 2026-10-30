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
use App\Models\LoadType;
use App\Models\Location;
use App\Models\User;
use App\Queries\AppointmentLocation;
use App\Session\AppointmentSession;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Collection;
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

    public function form(AppointmentSlotCapacity $slotCapacity, AppointmentScheduleResolver $scheduleResolver): Response
    {
        $locationDTO = $this->session->getLocation();

        $location = $locationDTO instanceof AppointmentLocationDTO
            ? resolve(AppointmentLocation::class)->execute($locationDTO->id)
            : null;

        if (! $locationDTO instanceof AppointmentLocationDTO || ! $location instanceof Location) {
            return inertia('AppointmentForm', [
                'location' => $locationDTO,
                'loadTypes' => [],
                'chooseLoadType' => false,
                'closingTimes' => [],
                'fullSlots' => [],
            ]);
        }

        // When staff set the load type, the driver never sees a choice: the form
        // gets that one alone, preselected, and its open times follow its length.
        $staffLoadType = $location->bookingLoadType();

        $loadTypes = $staffLoadType instanceof LoadType
            ? new Collection([$staffLoadType])
            : LoadType::query()->whereBelongsTo($location)->active()->orderBy('sort_order')->orderBy('name')->get();

        $durations = $loadTypes->isEmpty()
            ? [$location->appointmentDurationMinutes(null)]
            : array_values($loadTypes->map(fn (LoadType $loadType): int => $loadType->duration_minutes)->unique()->all());

        // Kept off the session DTO, which is serialized into the session, so
        // widening it would break bookings mid-flight across a deploy.
        return inertia('AppointmentForm', [
            'location' => $locationDTO,
            'loadTypes' => $loadTypes->map(fn (LoadType $loadType): array => [
                'id' => $loadType->uuid,
                'name' => $loadType->name,
                'direction' => $loadType->direction->value,
                'directionLabel' => $loadType->direction->label(),
                'durationMinutes' => $loadType->duration_minutes,
            ])->all(),
            'chooseLoadType' => ! $staffLoadType instanceof LoadType && $loadTypes->isNotEmpty(),
            // A booking has to finish by closing, so a long load cannot start late in the day.
            'closingTimes' => $scheduleResolver->closingTimes($location, $locationDTO->availability),
            // Read on every render rather than stored on the session DTO, so a
            // slot that fills while the driver is on the form shows as full on
            // reload. Keyed by booking length, since a long load needs room for longer.
            'fullSlots' => $slotCapacity->fullSlots($location, $locationDTO->availability, $locationDTO->slotIntervalMinutes, $durations),
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

        $validated = $request->booking();

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
}
