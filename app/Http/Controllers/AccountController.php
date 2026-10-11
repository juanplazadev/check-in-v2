<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Account\HistoryFilters;
use App\Account\HistoryRecordFinder;
use App\Account\HistoryRecordResolver;
use App\Account\HistoryResolver;
use App\Account\OverviewResolver;
use App\Actions\CancelAppointmentAction;
use App\Http\Requests\CancelAppointmentRequest;
use App\Http\Requests\HistoryFilterRequest;
use App\Http\Requests\HistoryRecordRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class AccountController extends Controller
{
    public function index(#[CurrentUser] User $user, OverviewResolver $resolver): Response
    {
        $expirationDate = $user->drivers_license_expiration_date;

        return inertia('Account', [
            ...$resolver->resolve($user),
            'licenseExpired' => $expirationDate !== null && $expirationDate->lt(today()),
        ]);
    }

    public function history(HistoryFilterRequest $request, #[CurrentUser] User $user, HistoryResolver $resolver): Response
    {
        $filters = HistoryFilters::fromRequest($request);

        return inertia('Account/History', [
            'filters' => $filters->toArray(...),
            'history' => Inertia::merge(fn (): array => $resolver->resolve($user, $filters))
                ->append('data', matchOn: 'key'),
        ]);
    }

    public function showCheckIn(
        HistoryRecordRequest $request,
        #[CurrentUser] User $user,
        HistoryRecordFinder $finder,
        HistoryRecordResolver $resolver,
    ): Response {
        return inertia('Account/HistoryCheckIn', [
            'checkIn' => $resolver->checkIn($finder->checkIn($user, $request->uuid())),
        ]);
    }

    public function showAppointment(
        HistoryRecordRequest $request,
        #[CurrentUser] User $user,
        HistoryRecordFinder $finder,
        HistoryRecordResolver $resolver,
    ): Response {
        return inertia('Account/HistoryAppointment', [
            'appointment' => $resolver->appointment($finder->appointment($user, $request->uuid())),
        ]);
    }

    /**
     * Let a driver give back a slot they booked.
     *
     * Only ever their own: the booking is resolved through
     * {@see HistoryRecordFinder}, which scopes by user_id and so 404s on
     * someone else's rather than 403ing and confirming it exists. Guest
     * bookings carry no user_id and are unreachable here by construction -
     * those drivers call the facility.
     *
     * @throws ValidationException
     * @throws Throwable
     */
    public function cancelAppointment(
        CancelAppointmentRequest $request,
        #[CurrentUser] User $user,
        HistoryRecordFinder $finder,
        CancelAppointmentAction $cancelAppointment,
    ): RedirectResponse {
        $appointment = $finder->appointment($user, $request->uuid());

        // Re-checked here and not only in the UI: the page could have been open
        // since before the slot passed, or the booking cancelled in another tab.
        if (! $cancelAppointment->isCancellable($appointment)) {
            throw ValidationException::withMessages([
                'reason' => __('messages.appointmentCancel.notCancellable'),
            ]);
        }

        $cancelAppointment->handle($appointment, $request->reason());

        // The page confirms via useForm's recentlySuccessful, the pattern the
        // rest of the account screens already use.
        return back();
    }

    public function editProfile(#[CurrentUser] User $user): Response
    {
        // The license number is hidden from the globally-shared auth.user, so
        // pass the decrypted value explicitly, only on the screen that edits it.
        return inertia('Account/EditProfile', [
            'license' => [
                'number' => $user->drivers_license_number,
                'state' => $user->drivers_license_state,
                'country' => $user->drivers_license_country,
                'expirationDate' => $user->drivers_license_expiration_date?->format('Y-m-d'),
            ],
        ]);
    }
}
