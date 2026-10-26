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
 * Pins the admin deployments page: the commit this server runs, matched to its
 * Sentry release, and each release's deploys, new issues and changelog grouped
 * by Conventional Commit type. Who may open it is pinned in DashboardPagesTest.
 */

const RUNNING_SHA = 'a1b2c3d4e5f60718293a4b5c6d7e8f9012345678';
const PREVIOUS_SHA = 'ffffeeeeddddccccbbbbaaaa9999888877776666';

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00', 'UTC'));

    config([
        'app.commit' => RUNNING_SHA,
        'services.sentry_api.url' => 'https://sentry.io',
        'services.sentry_api.token' => 'test-token',
        'services.sentry_api.org' => 'acme',
        'services.sentry_api.project' => 'check-in',
    ]);
});

/**
 * @param  Closure(AssertableInertia): AssertableInertia  $assert
 */
function deploymentsPage(Closure $assert): void
{
    test()->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->get(route('dashboard.deployments'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Dashboard/Deployments/Index')
            ->where('build', ['commit' => RUNNING_SHA, 'shortCommit' => 'a1b2c3d', 'environment' => 'testing'])
            ->missing('report')
            ->loadDeferredProps(fn (AssertableInertia $reload): AssertableInertia => $assert($reload)));
}

it('matches the running commit to its release and groups each changelog by commit type', function (): void {
    Http::fake([
        'sentry.io/api/0/projects/acme/check-in/' => Http::response(['id' => '17']),
        'sentry.io/api/0/organizations/acme/releases/?*' => Http::response([
            ['version' => RUNNING_SHA, 'dateCreated' => '2026-10-14T18:00:00Z', 'newGroups' => 2, 'commitCount' => 4, 'authors' => [['name' => 'Juan'], ['email' => 'dev@example.com']]],
            ['version' => PREVIOUS_SHA, 'dateCreated' => '2026-09-01T18:00:00Z', 'newGroups' => 5, 'commitCount' => 1, 'authors' => []],
        ]),
        'sentry.io/api/0/organizations/acme/releases/'.RUNNING_SHA.'/commits/' => Http::response([
            ['id' => 'c1aaaaaaaaaa', 'message' => "feat(roles)!: roles and permissions RUD\n\nDrops the old matrix.", 'author' => ['name' => 'Juan'], 'dateCreated' => '2026-10-14T17:00:00Z'],
            ['id' => 'c2bbbbbbbbbb', 'message' => 'feat: analytics page', 'author' => ['name' => 'Juan']],
            ['id' => 'c3cccccccccc', 'message' => 'fix: pull staging image from GHCR', 'author' => ['email' => 'dev@example.com']],
            ['id' => 'c4dddddddddd', 'message' => 'Merge branch main'],
        ]),
        'sentry.io/api/0/organizations/acme/releases/'.RUNNING_SHA.'/deploys/' => Http::response([
            ['environment' => 'staging', 'dateFinished' => '2026-10-14T18:05:00Z'],
            ['environment' => 'staging', 'dateFinished' => '2026-10-15T09:00:00Z'],
        ]),
        'sentry.io/api/0/organizations/acme/releases/'.PREVIOUS_SHA.'/commits/' => Http::response([]),
        'sentry.io/api/0/organizations/acme/releases/'.PREVIOUS_SHA.'/deploys/' => Http::response([
            ['environment' => 'staging', 'dateFinished' => '2026-09-01T18:05:00Z'],
        ]),
    ]);

    deploymentsPage(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('report.state', 'ready')
        ->where('report.data.running.version', RUNNING_SHA)
        ->where('report.data.running.shortVersion', 'a1b2c3d')
        ->where('report.data.running.deployedAt', '2026-10-15T09:00:00Z')
        ->where('report.data.running.environment', 'staging')
        ->where('report.data.running.newIssues', 2)
        ->where('report.data.running.health', 'watch')
        ->where('report.data.summary', [
            'lastDeployAt' => '2026-10-15T09:00:00Z',
            'lastDeployEnvironment' => 'staging',
            'deploysThisMonth' => 2,
            'releases' => 2,
            'issuesIntroduced' => 2,
        ])
        ->where('report.data.releases.0.isRunning', true)
        ->where('report.data.releases.0.authors', ['Juan', 'dev@example.com'])
        ->where('report.data.releases.0.changelog.breaking.0', [
            'id' => 'c1aaaaaaaaaa',
            'shortId' => 'c1aaaaa',
            'type' => 'feat',
            'scope' => 'roles',
            'subject' => 'roles and permissions RUD',
            'author' => 'Juan',
            'date' => '2026-10-14T17:00:00Z',
        ])
        ->where('report.data.releases.0.changelog.features.0.subject', 'analytics page')
        ->where('report.data.releases.0.changelog.fixes.0.author', 'dev@example.com')
        ->where('report.data.releases.0.changelog.other.0.subject', 'Merge branch main')
        ->where('report.data.releases.1.isRunning', false)
        ->where('report.data.releases.1.shortVersion', 'ffffeee')
        ->where('report.data.releases.1.issuesUrl', 'https://sentry.io/organizations/acme/issues/?query=firstRelease%3A'.PREVIOUS_SHA));
});

it('leaves running empty when the commit is not a registered release', function (): void {
    Http::fake([
        'sentry.io/api/0/projects/acme/check-in/' => Http::response(['id' => '17']),
        'sentry.io/api/0/organizations/acme/releases/?*' => Http::response([]),
    ]);

    deploymentsPage(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('report.data.running', null)
        ->where('report.data.releases', []));
});

it('rates the running release unhealthy from three new issues', function (): void {
    Http::fake([
        'sentry.io/api/0/projects/acme/check-in/' => Http::response(['id' => '17']),
        'sentry.io/api/0/organizations/acme/releases/?*' => Http::response([['version' => RUNNING_SHA, 'dateCreated' => '2026-10-14T18:00:00Z', 'newGroups' => 3]]),
        'sentry.io/api/0/organizations/acme/releases/'.RUNNING_SHA.'/*' => Http::response([]),
    ]);

    deploymentsPage(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('report.data.running.health', 'unhealthy'));
});

it('shows the unavailable state and reports the failure when Sentry errors', function (): void {
    Exceptions::fake();
    Http::fake(['sentry.io/api/0/projects/acme/check-in/' => Http::response(status: 503)]);

    deploymentsPage(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('report', ['state' => 'unavailable']));

    Exceptions::assertReported(RequestException::class);
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer test-token'));
});
