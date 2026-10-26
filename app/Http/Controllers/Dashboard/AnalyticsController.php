<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Enums\AnalyticsPeriod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\AnalyticsRequest;
use App\Models\User;
use App\Queries\DashboardAnalytics;
use App\Queries\DashboardLocations;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

final class AnalyticsController extends Controller
{
    /**
     * The report is deferred, so the header and filters paint before the
     * one pass over check-ins and appointments that every chart reads from.
     * The page reloads it in the browser's timezone when the link had none.
     */
    public function __invoke(
        #[CurrentUser] User $user,
        AnalyticsRequest $request,
        DashboardAnalytics $analytics,
        DashboardLocations $locations,
    ): Response {
        $filters = $request->filters();

        return inertia('Dashboard/Analytics/Index', [
            'filters' => [
                'period' => $filters['period']->value,
                'locations' => $filters['locations'],
                'timezone' => $filters['timezone'],
            ],
            'periods' => array_map(fn (AnalyticsPeriod $period): array => [
                'value' => $period->value,
                'label' => $period->label(),
                'previousLabel' => $period->previousLabel(),
            ], AnalyticsPeriod::cases()),
            'locations' => fn (): array => $locations->execute($user),
            'report' => Inertia::defer(fn (): array => $analytics->execute($user, $filters)),
        ]);
    }
}
