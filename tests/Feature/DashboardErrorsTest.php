<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

/*
 * Pins the admin errors pages: the headline figures and issue list read from
 * the Sentry API, the issue detail, and the states shown when Sentry is not
 * configured, cannot be reached, or no longer has the issue. Who may open the
 * pages is pinned with the other admin pages in DashboardPagesTest.
 */

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00', 'UTC'));

    config([
        'services.sentry_api.url' => 'https://sentry.io',
        'services.sentry_api.token' => 'test-token',
        'services.sentry_api.org' => 'acme',
        'services.sentry_api.project' => 'check-in',
        'services.sentry_api.error_quota' => 5000,
        'services.sentry_api.billing_day' => 1,
    ]);
});

/**
 * Daily outcome buckets from 2026-10-02 to 2026-10-15: the prior 7 days, then
 * the 7 days the page shows.
 *
 * @param  array<string, list<int>>  $series
 * @return array<string, mixed>
 */
function sentryOutcomes(array $series): array
{
    $intervals = array_map(
        fn (int $day): string => CarbonImmutable::parse('2026-10-02', 'UTC')->addDays($day)->toIso8601ZuluString(),
        range(0, 13),
    );

    return [
        'intervals' => $intervals,
        'groups' => array_map(fn (string $outcome, array $counts): array => [
            'by' => ['outcome' => $outcome],
            'totals' => ['sum(quantity)' => array_sum($counts)],
            'series' => ['sum(quantity)' => $counts],
        ], array_keys($series), array_values($series)),
    ];
}

/**
 * @return array<string, mixed>
 */
function sentryIssue(array $overrides = []): array
{
    return [
        'id' => '4501',
        'shortId' => 'CHECK-IN-1A',
        'title' => 'ErrorException: Undefined array key "dock"',
        'culprit' => 'App\Actions\CompleteCheckInAction in handle',
        'metadata' => ['type' => 'ErrorException', 'value' => 'Undefined array key "dock"'],
        'level' => 'error',
        'status' => 'unresolved',
        'substatus' => 'new',
        'isUnhandled' => true,
        'count' => '42',
        'userCount' => 7,
        'firstSeen' => '2026-10-14T09:00:00Z',
        'lastSeen' => '2026-10-15T11:30:00Z',
        'permalink' => 'https://acme.sentry.io/issues/4501/',
        'stats' => ['auto' => [[1760486400, 3], [1760572800, 5]]],
        ...$overrides,
    ];
}

/**
 * Fakes the Sentry API by path; each handler gets the request and returns
 * the response. Any other path fails through preventStrayRequests().
 *
 * @param  array<string, Closure(Request): mixed>  $routes
 */
function fakeSentry(array $routes): void
{
    Http::fake(function (Request $request) use ($routes) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        foreach ($routes as $route => $respond) {
            if ($path === '/api/0'.$route) {
                return $respond($request);
            }
        }

        return null;
    });
}

/**
 * @return array<string, Closure(Request): mixed>
 */
function sentryReportRoutes(?Closure $events = null): array
{
    return [
        '/projects/acme/check-in/' => fn (): mixed => Http::response(['id' => '17', 'slug' => 'check-in']),
        '/projects/acme/check-in/environments/' => fn (): mixed => Http::response([['name' => 'production'], ['name' => 'staging']]),
        '/organizations/acme/stats_v2/' => fn (Request $request): mixed => $request->data()['start'] === '2026-10-01T00:00:00Z'
            ? Http::response(sentryOutcomes(['accepted' => [1200]]))
            : Http::response(sentryOutcomes([
                'accepted' => [1, 1, 1, 1, 1, 1, 1, 10, 10, 10, 10, 10, 10, 10],
                'filtered' => [0, 0, 0, 0, 0, 0, 0, 2, 2, 2, 2, 2, 2, 2],
                'rate_limited' => [3, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1],
            ])),
        '/organizations/acme/events/' => $events ?? fn (): mixed => Http::response(['data' => [['count_unique(user)' => 23]]]),
        '/organizations/acme/issues/' => fn (Request $request): mixed => Http::response(
            [sentryIssue(['userCount' => 9])],
            headers: ['X-Hits' => str_contains((string) $request->data()['query'], 'firstSeen') ? '4' : '31'],
        ),
    ];
}

/**
 * @param  array<string, mixed>  $query
 */
function errorsPage(array $query, Closure $assert): void
{
    test()->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->get(route('dashboard.errors', $query))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Dashboard/Errors/Index')
            ->missing('report')
            ->missing('issues')
            ->loadDeferredProps(fn (AssertableInertia $reload): AssertableInertia => $assert($reload)));
}

it('compares errors and dropped events with the previous period and counts quota from the billing day', function (): void {
    fakeSentry(sentryReportRoutes());

    errorsPage(['period' => '7d'], fn (AssertableInertia $page): AssertableInertia => $page
        ->where('report.state', 'ready')
        ->where('report.data.summary.errors', ['value' => 70, 'previous' => 7])
        ->where('report.data.summary.dropped', ['value' => 15, 'previous' => 3, 'breakdown' => ['filtered' => 14, 'rate_limited' => 1]])
        ->where('report.data.summary.usersAffected', ['value' => 23, 'exact' => true])
        ->where('report.data.summary.unresolved', 31)
        ->where('report.data.summary.newIssues', 4)
        ->where('report.data.volume.accepted', [10, 10, 10, 10, 10, 10, 10])
        ->where('report.data.volume.dropped', [2, 2, 2, 2, 2, 2, 3])
        ->has('report.data.volume.labels', 7)
        ->where('report.data.quota.used', 1200)
        ->where('report.data.quota.limit', 5000)
        ->where('report.data.quota.percent', 24)
        ->where('report.data.quota.daysUntilReset', 17)
        ->where('report.data.environments', ['production', 'staging']));
});

it('falls back to the most users one issue hit when the events API refuses the query', function (): void {
    fakeSentry(sentryReportRoutes(fn (): mixed => Http::response(['detail' => 'No access'], 403)));

    errorsPage([], fn (AssertableInertia $page): AssertableInertia => $page
        ->where('report.data.summary.usersAffected', ['value' => 9, 'exact' => false]));
});

it("lists the tab's issues with the admin's search, sort and environment", function (): void {
    fakeSentry(sentryReportRoutes());

    errorsPage(
        ['period' => '24h', 'tab' => 'new', 'sort' => 'freq', 'environment' => 'production', 'search' => 'dock'],
        fn (AssertableInertia $page): AssertableInertia => $page
            ->where('issues.state', 'ready')
            ->where('issues.data.query', 'is:unresolved firstSeen:-24h dock')
            ->where('issues.data.total', 4)
            ->where('issues.data.rows.0', [
                'id' => '4501',
                'shortId' => 'CHECK-IN-1A',
                'title' => 'ErrorException: Undefined array key "dock"',
                'culprit' => 'App\Actions\CompleteCheckInAction in handle',
                'type' => 'ErrorException',
                'level' => 'error',
                'status' => 'unresolved',
                'substatus' => 'new',
                'isUnhandled' => true,
                'events' => 42,
                'users' => 9,
                'firstSeen' => '2026-10-14T09:00:00Z',
                'lastSeen' => '2026-10-15T11:30:00Z',
                'trend' => [3, 5],
                'permalink' => 'https://acme.sentry.io/issues/4501/',
            ]),
    );

    Http::assertSent(fn (Request $request): bool => str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/organizations/acme/issues/')
        && $request->data()['query'] === 'is:unresolved firstSeen:-24h dock'
        && $request->data()['sort'] === 'freq'
        && $request->data()['environment'] === 'production'
        && $request->data()['project'] === 17
        && $request->hasHeader('Authorization', 'Bearer test-token'));
});

it('shows the unavailable state and reports the failure when Sentry errors', function (): void {
    Exceptions::fake();
    fakeSentry(['/projects/acme/check-in/' => fn (): mixed => Http::response(status: 500)]);

    errorsPage([], fn (AssertableInertia $page): AssertableInertia => $page
        ->where('report', ['state' => 'unavailable'])
        ->where('issues', ['state' => 'unavailable']));

    Exceptions::assertReported(RequestException::class);
});

it('accepts a Sentry URL configured with the API path', function (): void {
    config(['services.sentry_api.url' => 'https://sentry.io/api/0/']);
    fakeSentry(sentryReportRoutes());

    errorsPage([], fn (AssertableInertia $page): AssertableInertia => $page
        ->where('report.state', 'ready')
        ->where('issues.state', 'ready'));

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://sentry.io/api/0/projects/acme/check-in/');
});

it('shows the unconfigured state without calling Sentry when the token is missing', function (): void {
    config(['services.sentry_api.token' => null]);

    errorsPage([], fn (AssertableInertia $page): AssertableInertia => $page
        ->where('report', ['state' => 'unconfigured'])
        ->where('issues', ['state' => 'unconfigured']));

    Http::assertNothingSent();
});

it('rejects a period or sort outside the allowlist', function (string $field, string $value): void {
    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->get(route('dashboard.errors', [$field => $value]))
        ->assertSessionHasErrors($field);
})->with([
    'period' => ['period', '90d'],
    'sort' => ['sort', 'priority'],
    'tab' => ['tab', 'archived'],
    'cursor' => ['cursor', 'abc'],
]);

it("shows an issue's latest event with its frames most recent call first", function (): void {
    fakeSentry([
        '/organizations/acme/issues/4501/' => fn (): mixed => Http::response(sentryIssue([
            'firstRelease' => ['version' => 'abc1234'],
            'stats' => ['30d' => [[1760486400, 3]]],
        ])),
        '/organizations/acme/issues/4501/events/latest/' => fn (): mixed => Http::response([
            'eventID' => 'e1',
            'dateCreated' => '2026-10-15T11:30:00Z',
            'tags' => [['key' => 'environment', 'value' => 'production'], ['key' => 'release', 'value' => 'abc1234']],
            'contexts' => ['runtime' => ['name' => 'php', 'version' => '8.5.1']],
            'user' => ['id' => '12', 'email' => null],
            'entries' => [
                ['type' => 'exception', 'data' => ['values' => [[
                    'type' => 'ErrorException',
                    'value' => 'Undefined array key "dock"',
                    'mechanism' => ['type' => 'generic', 'handled' => false],
                    'stacktrace' => ['frames' => [
                        ['filename' => 'vendor/laravel/framework/Pipeline.php', 'function' => 'handle', 'lineNo' => 10, 'inApp' => false],
                        ['filename' => 'app/Actions/CompleteCheckInAction.php', 'function' => 'handle', 'lineNo' => 31, 'inApp' => true, 'context' => [[31, '$dock = $data["dock"];']]],
                    ]],
                ]]]],
                ['type' => 'request', 'data' => ['method' => 'POST', 'url' => 'https://checkin.test/dashboard/check-ins/1/complete']],
            ],
        ]),
        '/organizations/acme/issues/4501/tags/' => fn (): mixed => Http::response([
            ['key' => 'browser', 'name' => 'Browser', 'totalValues' => 40, 'topValues' => [['value' => 'Chrome 141', 'count' => 30]]],
            ['key' => 'mechanism', 'name' => 'Mechanism', 'totalValues' => 40, 'topValues' => []],
        ]),
    ]);

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->get(route('dashboard.errors.show', ['issueId' => 4501]))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Dashboard/Errors/Show')
            ->missing('detail')
            ->loadDeferredProps(fn (AssertableInertia $reload): AssertableInertia => $reload
                ->where('detail.state', 'ready')
                ->where('detail.data.issue.firstRelease', 'abc1234')
                ->where('detail.data.issue.trend', ['labels' => [1760486400000], 'counts' => [3]])
                ->where('detail.data.event.environment', 'production')
                ->where('detail.data.event.exceptions.0.handled', false)
                ->where('detail.data.event.exceptions.0.frames.0.filename', 'app/Actions/CompleteCheckInAction.php')
                ->where('detail.data.event.exceptions.0.frames.0.context', [['line' => 31, 'code' => '$dock = $data["dock"];']])
                ->where('detail.data.event.exceptions.0.frames.1.inApp', false)
                ->where('detail.data.event.request', ['method' => 'POST', 'url' => 'https://checkin.test/dashboard/check-ins/1/complete'])
                ->where('detail.data.event.user', ['id' => '12', 'email' => null, 'username' => null, 'ip' => null])
                ->where('detail.data.event.contexts', [['key' => 'runtime', 'value' => 'php 8.5.1']])
                ->where('detail.data.tags', [['key' => 'browser', 'name' => 'Browser', 'total' => 40, 'top' => [['value' => 'Chrome 141', 'count' => 30, 'percent' => 75]]]])));
});

it('shows the missing state when Sentry no longer has the issue', function (): void {
    fakeSentry([
        '/organizations/acme/issues/4501/' => fn (): mixed => Http::response(['detail' => 'Not found'], 404),
        '/organizations/acme/issues/4501/events/latest/' => fn (): mixed => Http::response(['detail' => 'Not found'], 404),
        '/organizations/acme/issues/4501/tags/' => fn (): mixed => Http::response(['detail' => 'Not found'], 404),
    ]);

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->get(route('dashboard.errors.show', ['issueId' => 4501]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->loadDeferredProps(fn (AssertableInertia $reload): AssertableInertia => $reload
                ->where('detail', ['state' => 'missing'])));
});
