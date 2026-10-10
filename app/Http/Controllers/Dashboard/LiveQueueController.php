<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Dashboard\AppointmentRecordResolver;
use App\Dashboard\CheckInRecordResolver;
use App\Http\Controllers\Controller;
use App\Queries\DashboardLocations;
use App\Queries\LiveQueueAppointments;
use App\Queries\LiveQueueCheckIns;
use Inertia\Response;

/**
 * The screen shipping leaves open: pending check-ins and the appointments
 * about to arrive, kept current over the websocket. These props are the starting state and the catch-up after a
 * dropped connection.
 */
final class LiveQueueController extends Controller
{
    public function __invoke(
        LiveQueueCheckIns $checkIns,
        LiveQueueAppointments $appointments,
        DashboardLocations $locations,
        CheckInRecordResolver $checkInResolver,
        AppointmentRecordResolver $appointmentResolver,
    ): Response {
        return inertia('Dashboard/Queue/Index', [
            'checkIns' => $checkIns->execute()->map($checkInResolver->record(...))->all(),
            'windowHours' => LiveQueueCheckIns::WINDOW_HOURS,
            'appointments' => $appointments->execute()->map($appointmentResolver->record(...))->all(),
            'appointmentWindow' => [
                'lateGraceHours' => LiveQueueAppointments::LATE_GRACE_HOURS,
                'aheadHours' => LiveQueueAppointments::AHEAD_HOURS,
            ],
            'locations' => fn (): array => $locations->execute(),
        ]);
    }
}
