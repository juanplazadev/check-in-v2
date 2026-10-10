<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Dashboard\AppointmentRecordResolver;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Inertia\Response;

final class AppointmentController extends Controller
{
    public function show(string $uuid, AppointmentRecordResolver $resolver): Response
    {
        $appointment = Appointment::query()
            ->with([...AppointmentRecordResolver::RELATIONS, 'history.user'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        return inertia('Dashboard/Appointments/Show', [
            'appointment' => $resolver->record($appointment),
        ]);
    }
}
