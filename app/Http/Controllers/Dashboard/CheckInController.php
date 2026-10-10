<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Dashboard\CheckInRecordResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\CheckInIndexRequest;
use App\Models\CheckIn;
use App\Models\User;
use App\Queries\DashboardCheckInColumns;
use App\Queries\DashboardCheckIns;
use App\Queries\DashboardLocations;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Response;

final class CheckInController extends Controller
{
    /**
     * Both table props are closures, so the live feed's statusCounts-only reload
     * skips the page query. The counts run once and give the page its total.
     */
    public function index(
        #[CurrentUser] User $user,
        CheckInIndexRequest $request,
        DashboardCheckIns $query,
        DashboardLocations $locations,
        DashboardCheckInColumns $columns,
        CheckInRecordResolver $resolver,
    ): Response {
        $filters = $request->filters();
        $counts = null;
        $statusCounts = function () use (&$counts, $query, $user, $filters): array {
            return $counts ??= $query->statusCounts($user, $filters);
        };

        return inertia('Dashboard/CheckIns/Index', [
            'checkIns' => fn (): LengthAwarePaginator => $query
                ->execute($user, $filters, $statusCounts()[$filters['status'] ?? 'all'])
                ->through($resolver->record(...)),
            'statusCounts' => $statusCounts,
            'filters' => $filters,
            'searchMinLength' => DashboardCheckIns::MIN_SEARCH_LENGTH,
            'pageSizes' => fn (): array => [
                'options' => DashboardCheckIns::PER_PAGE_OPTIONS,
                'default' => DashboardCheckIns::DEFAULT_PER_PAGE,
            ],
            'locations' => fn (): array => $locations->execute($user),
            'columns' => fn (): array => $columns->execute($user),
        ]);
    }

    /**
     * A check-in at a location the user may not see is a 404, not a 403, so
     * its existence is not confirmed. Archived check-ins still open, since the
     * archived tab links to them.
     */
    public function show(#[CurrentUser] User $user, string $uuid, CheckInRecordResolver $resolver): Response
    {
        $checkIn = CheckIn::query()
            ->withTrashed()
            ->visibleTo($user)
            ->with([...CheckInRecordResolver::RELATIONS, 'history.user'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        return inertia('Dashboard/CheckIns/Show', [
            'checkIn' => $resolver->record($checkIn),
        ]);
    }
}
