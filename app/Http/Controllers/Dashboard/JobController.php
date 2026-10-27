<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Dashboard\HorizonPayload;
use App\Http\Controllers\Controller;
use App\Queries\HorizonJobsReport;
use Inertia\Inertia;
use Inertia\Response;

final class JobController extends Controller
{
    /**
     * The queue report reads Redis, so it loads deferred; the page polls it
     * to stay live.
     */
    public function index(HorizonPayload $payload, HorizonJobsReport $report): Response
    {
        return inertia('Dashboard/Jobs/Index', [
            'environment' => config('app.env'),
            'report' => Inertia::defer(fn (): array => $payload->resolve(fn (): array => $report->execute())),
        ]);
    }
}
