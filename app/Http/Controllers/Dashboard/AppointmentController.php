<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Dashboard\AppointmentRecordResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\AppointmentIndexRequest;
use App\Models\Appointment;
use App\Models\User;
use App\Queries\DashboardAppointmentColumns;
use App\Queries\DashboardAppointments;
use App\Queries\DashboardLocations;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Response;

final class AppointmentController extends Controller
{
    /**
     * Both table props are closures, so a partial reload skips what it does not
     * ask for. The counts run once and give the page its total.
     *
     * The location filter offers every location the user may see, not only the
     * ones taking bookings today, since a location that turned appointments off
     * still has the ones it took before.
     */
    public function index(
        #[CurrentUser] User $user,
        AppointmentIndexRequest $request,
        DashboardAppointments $query,
        DashboardLocations $locations,
        DashboardAppointmentColumns $columns,
        AppointmentRecordResolver $resolver,
    ): Response {
        $filters = $request->filters();
        $counts = null;
        $statusCounts = function () use (&$counts, $query, $user, $filters): array {
            return $counts ??= $query->statusCounts($user, $filters);
        };

        return inertia('Dashboard/Appointments/Index', [
            'appointments' => fn (): LengthAwarePaginator => $query
                ->execute($user, $filters, $statusCounts()[$filters['status'] ?? 'all'])
                ->through($resolver->record(...)),
            'statusCounts' => $statusCounts,
            'filters' => $filters,
            'searchMinLength' => DashboardAppointments::MIN_SEARCH_LENGTH,
            'pageSizes' => fn (): array => [
                'options' => DashboardAppointments::PER_PAGE_OPTIONS,
                'default' => DashboardAppointments::DEFAULT_PER_PAGE,
            ],
            'locations' => fn (): array => $locations->execute($user),
            'columns' => fn (): array => $columns->execute($user),
        ]);
    }

    /**
     * An appointment at a location the user may not see is a 404, not a 403,
     * so its existence is not confirmed. Archived appointments still open,
     * since the archived tab links to them.
     */
    public function show(#[CurrentUser] User $user, string $uuid, AppointmentRecordResolver $resolver): Response
    {
        $appointment = Appointment::query()
            ->withTrashed()
            ->visibleTo($user)
            ->with([...AppointmentRecordResolver::RELATIONS, 'history.user'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        return inertia('Dashboard/Appointments/Show', [
            'appointment' => $resolver->record($appointment),
        ]);
    }
}
