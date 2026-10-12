<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CompleteCheckInAction;
use App\Auth\RegistrationGate;
use App\CheckIn\CheckInConfirmationResolver;
use App\CheckIn\CheckInScheduleResolver;
use App\DTOs\CheckInLocationDTO;
use App\Http\Requests\CheckInFormRequest;
use App\Http\Requests\CheckInLocationSelectRequest;
use App\Models\Location;
use App\Models\User;
use App\Session\CheckInSession;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;
use Throwable;

final class CheckInController extends Controller
{
    public function __construct(private readonly CheckInSession $session) {}

    public function selectLocation(CheckInScheduleResolver $resolver): Response
    {
        return inertia('CheckInSelectLocation', [
            'locations' => $resolver->getLocations(),
        ]);
    }

    public function gate(CheckInLocationSelectRequest $request, CheckInScheduleResolver $resolver): RedirectResponse
    {
        $request->validated();

        $location = $request->location;
        assert($location instanceof Location);

        // Proximity check has passed; issue a gate pass carrying the resolved
        // location so the driver can complete the (potentially long) form
        // without re-querying or re-validating coords.
        $this->session->issueGatePass(
            $resolver->buildDTO($location, now()),
            $location->checkInFormFields(),
        );

        return to_route('checkIn.form', $location->uuid);
    }

    public function form(#[CurrentUser] ?User $user = null): RedirectResponse|Response
    {
        $location = $this->session->getLocation();

        if (! $location instanceof CheckInLocationDTO) {
            return to_route('checkIn.selectLocation')
                ->withErrors(['uuid' => __('messages.checkInSelectLocation.invalidLocation')]);
        }

        return inertia('CheckInForm', [
            'location' => $location,
            'fields' => $this->session->getCheckInFormFields() ?? [],
            'truckColors' => config('app.truck_colors'),
            // Saved license (number is hidden from the global auth.user share) so
            // a signed-in driver's check-in prefills from their profile.
            'driverLicense' => $user instanceof User ? [
                'number' => $user->drivers_license_number,
                'state' => $user->drivers_license_state,
                'country' => $user->drivers_license_country,
                'expirationDate' => $user->drivers_license_expiration_date?->format('Y-m-d'),
            ] : null,
        ]);
    }

    /**
     * @throws Throwable
     */
    public function store(
        CheckInFormRequest $request,
        CompleteCheckInAction $action,
        RegistrationGate $registrationGate,
        #[CurrentUser] ?User $user = null
    ): RedirectResponse {
        $locationDTO = $this->session->getLocation();
        if (! $locationDTO instanceof CheckInLocationDTO) {
            return to_route('checkIn.selectLocation');
        }

        $validated = $request->validated();

        $checkIn = $action->handle($validated, $locationDTO, $user);

        $this->session->forgetGatePass();

        // Open the short registration window, carrying the driver's cellphone so
        // a follow-up account is prefilled with it.
        $registrationGate->allow(
            $validated['drivers_cellphone'] ?? null,
            $validated['drivers_name'] ?? null,
        );
        $registrationGate->claim('check_in', $checkIn->id);

        return to_route('checkIn.confirmed', $checkIn->uuid);
    }

    public function confirmed(string $uuid, CheckInConfirmationResolver $resolver): Response
    {
        return inertia('CheckInConfirmation', $resolver->resolve($uuid));
    }
}
