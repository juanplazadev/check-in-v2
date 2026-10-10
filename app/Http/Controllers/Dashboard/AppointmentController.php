<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Dashboard\AppointmentRecordResolver;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Response;

final class AppointmentController extends Controller
{
    /**
     * An appointment at a location the user may not see is a 404, not a 403,
     * so its existence is not confirmed.
     */
    public function show(#[CurrentUser] User $user, string $uuid, AppointmentRecordResolver $resolver): Response
    {
        $appointment = Appointment::query()
            ->visibleTo($user)
            ->with([...AppointmentRecordResolver::RELATIONS, 'history.user'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        return inertia('Dashboard/Appointments/Show', [
            'appointment' => $resolver->record($appointment),
        ]);
    }
}
