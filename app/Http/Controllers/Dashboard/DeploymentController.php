<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Dashboard\SentryPayload;
use App\Http\Controllers\Controller;
use App\Queries\SentryDeploymentsReport;
use Inertia\Inertia;
use Inertia\Response;

final class DeploymentController extends Controller
{
    /**
     * The commit this server was built from paints at once; the releases,
     * deploys and changelogs come from Sentry deferred.
     */
    public function __invoke(SentryPayload $payload, SentryDeploymentsReport $report): Response
    {
        $commit = config('app.commit');
        $commit = is_string($commit) && $commit !== '' && $commit !== 'unknown' ? $commit : null;

        return inertia('Dashboard/Deployments/Index', [
            'build' => [
                'commit' => $commit,
                'shortCommit' => $commit === null ? null : mb_substr($commit, 0, 7),
                'environment' => config('app.env'),
            ],
            'report' => Inertia::defer(fn (): array => $payload->resolve(fn (): array => $report->execute($commit))),
        ]);
    }
}
