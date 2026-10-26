<?php

declare(strict_types=1);

namespace App\Queries;

use App\Dashboard\ConventionalCommit;
use App\Services\SentryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;

/**
 * The deployments page, from the Sentry releases the deploy workflow
 * registers (one per commit SHA, with its commits and deploys). Cached for
 * five minutes.
 *
 * Each release's changelog is its commits grouped by Conventional Commit type.
 * New issues are the issues Sentry first saw in that release, which is how
 * healthy the running release looks: none is healthy, one or two is worth a
 * look, three or more is unhealthy.
 */
final readonly class SentryDeploymentsReport
{
    private const int RELEASE_LIMIT = 20;

    private const int WATCH_THRESHOLD = 1;

    private const int UNHEALTHY_THRESHOLD = 3;

    public function __construct(private SentryService $sentry) {}

    /**
     * @return array{
     *     running: array{version: string, shortVersion: string, deployedAt: ?string, environment: ?string, newIssues: int, health: string, url: string, issuesUrl: string}|null,
     *     summary: array{lastDeployAt: ?string, lastDeployEnvironment: ?string, deploysThisMonth: int, releases: int, issuesIntroduced: int},
     *     releases: list<array<string, mixed>>,
     * }
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function execute(?string $runningCommit): array
    {
        $releases = Cache::remember('sentry:releases', now()->addMinutes(5), function (): array {
            $releases = $this->sentry->releases(self::RELEASE_LIMIT);
            $details = $this->sentry->releaseDetails(array_map(fn (array $release): string => (string) $release['version'], $releases));

            return array_map(
                fn (array $release): array => $this->release($release, $details[(string) $release['version']] ?? ['commits' => [], 'deploys' => []]),
                $releases,
            );
        });

        $releases = array_map(fn (array $release): array => [
            ...$release,
            'isRunning' => $runningCommit !== null && $release['version'] === $runningCommit,
        ], $releases);

        return [
            'running' => $this->running($releases),
            'summary' => $this->summary($releases),
            'releases' => $releases,
        ];
    }

    /**
     * @param  array<string, mixed>  $release
     * @param  array{commits: list<array<string, mixed>>, deploys: list<array<string, mixed>>}  $details
     * @return array<string, mixed>
     */
    private function release(array $release, array $details): array
    {
        $version = (string) $release['version'];
        $changelog = ['breaking' => [], 'features' => [], 'fixes' => [], 'other' => []];

        foreach ($details['commits'] as $commit) {
            $parsed = ConventionalCommit::fromMessage((string) ($commit['message'] ?? ''));

            $changelog[$parsed->group()][] = [
                'id' => (string) $commit['id'],
                'shortId' => mb_substr((string) $commit['id'], 0, 7),
                'type' => $parsed->type,
                'scope' => $parsed->scope,
                'subject' => $parsed->subject,
                'author' => $commit['author']['name'] ?? $commit['author']['email'] ?? null,
                'date' => $commit['dateCreated'] ?? null,
            ];
        }

        $deploys = array_map(fn (array $deploy): array => [
            'environment' => (string) ($deploy['environment'] ?? 'unknown'),
            'finishedAt' => $deploy['dateFinished'] ?? $deploy['dateStarted'] ?? null,
        ], $details['deploys']);

        usort($deploys, fn (array $a, array $b): int => strcmp((string) $b['finishedAt'], (string) $a['finishedAt']));

        return [
            'version' => $version,
            'shortVersion' => $this->shortVersion($version),
            'createdAt' => $release['dateCreated'] ?? null,
            'newIssues' => (int) ($release['newGroups'] ?? 0),
            'commitCount' => (int) ($release['commitCount'] ?? count($details['commits'])),
            'authors' => array_values(array_unique(array_filter(array_map(
                fn (array $author): ?string => $author['name'] ?? $author['email'] ?? null,
                $release['authors'] ?? [],
            )))),
            'deploys' => $deploys,
            'changelog' => $changelog,
            'url' => $this->sentry->webUrl('releases/'.rawurlencode($version).'/'),
            'issuesUrl' => $this->sentry->webUrl('issues/?query='.rawurlencode('firstRelease:'.$version)),
        ];
    }

    /**
     * The release this server runs, or, when the commit is unknown (local
     * dev) or not registered, null.
     *
     * @param  list<array<string, mixed>>  $releases
     * @return array{version: string, shortVersion: string, deployedAt: ?string, environment: ?string, newIssues: int, health: string, url: string, issuesUrl: string}|null
     */
    private function running(array $releases): ?array
    {
        $release = array_find($releases, fn (array $release): bool => $release['isRunning']);

        if ($release === null) {
            return null;
        }

        $lastDeploy = $release['deploys'][0] ?? null;

        return [
            'version' => $release['version'],
            'shortVersion' => $release['shortVersion'],
            'deployedAt' => $lastDeploy['finishedAt'] ?? $release['createdAt'],
            'environment' => $lastDeploy['environment'] ?? null,
            'newIssues' => $release['newIssues'],
            'health' => match (true) {
                $release['newIssues'] >= self::UNHEALTHY_THRESHOLD => 'unhealthy',
                $release['newIssues'] >= self::WATCH_THRESHOLD => 'watch',
                default => 'healthy',
            },
            'url' => $release['url'],
            'issuesUrl' => $release['issuesUrl'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $releases
     * @return array{lastDeployAt: ?string, lastDeployEnvironment: ?string, deploysThisMonth: int, releases: int, issuesIntroduced: int}
     */
    private function summary(array $releases): array
    {
        $deploys = array_merge(...array_map(fn (array $release): array => $release['deploys'], $releases));
        usort($deploys, fn (array $a, array $b): int => strcmp((string) $b['finishedAt'], (string) $a['finishedAt']));

        $monthStart = Date::now()->toImmutable()->startOfMonth();
        $thirtyDaysAgo = Date::now()->toImmutable()->subDays(30);

        return [
            'lastDeployAt' => $deploys[0]['finishedAt'] ?? null,
            'lastDeployEnvironment' => $deploys[0]['environment'] ?? null,
            'deploysThisMonth' => count(array_filter(
                $deploys,
                fn (array $deploy): bool => $deploy['finishedAt'] !== null && CarbonImmutable::parse($deploy['finishedAt'])->greaterThanOrEqualTo($monthStart),
            )),
            'releases' => count($releases),
            'issuesIntroduced' => array_sum(array_map(
                fn (array $release): int => $release['createdAt'] !== null && CarbonImmutable::parse($release['createdAt'])->greaterThanOrEqualTo($thirtyDaysAgo) ? $release['newIssues'] : 0,
                $releases,
            )),
        ];
    }

    /**
     * A commit SHA shortened to seven characters; other versions as they are.
     */
    private function shortVersion(string $version): string
    {
        return preg_match('/^[0-9a-f]{40}$/', $version) === 1 ? mb_substr($version, 0, 7) : $version;
    }
}
