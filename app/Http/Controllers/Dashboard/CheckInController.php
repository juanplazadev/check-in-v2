<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Dashboard\CheckInRecordResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\CheckInIndexRequest;
use App\Models\CheckIn;
use App\Models\User;
use App\Queries\DashboardCheckIns;
use App\Queries\DashboardLocations;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Response;

final class CheckInController extends Controller
{
    public function index(
        #[CurrentUser] User $user,
        CheckInIndexRequest $request,
        DashboardCheckIns $query,
        DashboardLocations $locations,
        CheckInRecordResolver $resolver,
    ): Response {
        $filters = $request->filters();

        return inertia('Dashboard/CheckIns/Index', [
            'checkIns' => $query->execute($user, $filters)->through($resolver->record(...)),
            'statusCounts' => $query->statusCounts($user, $filters),
            'filters' => $filters,
            'locations' => fn (): array => $locations->execute($user),
        ]);
    }

    /**
     * A check-in at a location the user may not see is a 404, not a 403, so
     * its existence is not confirmed.
     */
    public function show(#[CurrentUser] User $user, string $uuid, CheckInRecordResolver $resolver): Response
    {
        $checkIn = CheckIn::query()
            ->visibleTo($user)
            ->with([...CheckInRecordResolver::RELATIONS, 'history.user'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        return inertia('Dashboard/CheckIns/Show', [
            'checkIn' => $resolver->record($checkIn),
        ]);
    }
}
