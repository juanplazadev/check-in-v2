<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Dashboard\SentryPayload;
use App\Enums\ErrorIssueSort;
use App\Enums\ErrorIssueTab;
use App\Enums\ErrorPeriod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\ErrorsRequest;
use App\Queries\SentryErrorsReport;
use App\Queries\SentryIssueDetail;
use App\Queries\SentryIssues;
use App\Services\SentryService;
use Inertia\Inertia;
use Inertia\Response;

final class ErrorController extends Controller
{
    /**
     * The headline report and the issue list are deferred apart, so changing
     * the tab, sort or page reloads only the issues.
     */
    public function index(
        ErrorsRequest $request,
        SentryPayload $payload,
        SentryErrorsReport $report,
        SentryIssues $issues,
        SentryService $sentry,
    ): Response {
        $filters = $request->filters();

        return inertia('Dashboard/Errors/Index', [
            'filters' => [
                'period' => $filters['period']->value,
                'tab' => $filters['tab']->value,
                'sort' => $filters['sort']->value,
                'environment' => $filters['environment'],
                'search' => $filters['search'],
                'cursor' => $filters['cursor'],
            ],
            'defaultEnvironment' => ErrorsRequest::defaultEnvironment(),
            'periods' => array_map(fn (ErrorPeriod $period): array => [
                'value' => $period->value,
                'label' => $period->label(),
                'previousLabel' => $period->previousLabel(),
            ], ErrorPeriod::cases()),
            'tabs' => array_map(fn (ErrorIssueTab $tab): array => ['value' => $tab->value, 'label' => $tab->label()], ErrorIssueTab::cases()),
            'sorts' => array_map(fn (ErrorIssueSort $sort): array => ['value' => $sort->value, 'label' => $sort->label()], ErrorIssueSort::cases()),
            'sentryUrl' => $sentry->isConfigured() ? $sentry->webUrl('issues/') : null,
            'report' => Inertia::defer(fn (): array => $payload->resolve(fn (): array => $report->execute($filters))),
            'issues' => Inertia::defer(fn (): array => $payload->resolve(fn (): array => $issues->execute($filters)), 'issues'),
        ]);
    }

    public function show(int $issueId, SentryPayload $payload, SentryIssueDetail $detail): Response
    {
        return inertia('Dashboard/Errors/Show', [
            'issueId' => $issueId,
            'detail' => Inertia::defer(fn (): array => $payload->resolve(fn (): ?array => $detail->execute($issueId))),
        ]);
    }
}
