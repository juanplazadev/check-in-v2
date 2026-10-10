<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Dashboard\CheckInRecordResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\CheckInIndexRequest;
use App\Models\CheckIn;
use App\Queries\DashboardCheckIns;
use App\Queries\DashboardLocations;
use Inertia\Response;

final class CheckInController extends Controller
{
    public function index(
        CheckInIndexRequest $request,
        DashboardCheckIns $query,
        DashboardLocations $locations,
        CheckInRecordResolver $resolver,
    ): Response {
        $filters = $request->filters();

        return inertia('Dashboard/CheckIns/Index', [
            'checkIns' => $query->execute($filters)->through($resolver->record(...)),
            'statusCounts' => $query->statusCounts($filters),
            'filters' => $filters,
            'locations' => fn (): array => $locations->execute(),
        ]);
    }

    public function show(string $uuid, CheckInRecordResolver $resolver): Response
    {
        $checkIn = CheckIn::query()
            ->with([...CheckInRecordResolver::RELATIONS, 'history.user'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        return inertia('Dashboard/CheckIns/Show', [
            'checkIn' => $resolver->record($checkIn),
        ]);
    }
}
