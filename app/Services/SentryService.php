<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * A thin, read-only client for the Sentry REST API: issues, their latest
 * event, error outcomes (accepted and dropped), releases and deploys. Returns
 * Sentry's JSON as arrays; the Sentry* queries shape it for the pages.
 */
final readonly class SentryService
{
    public function isConfigured(): bool
    {
        return filled(config('services.sentry_api.token'))
            && filled(config('services.sentry_api.org'))
            && filled(config('services.sentry_api.project'));
    }

    /**
     * The configured project's numeric id, which the organization endpoints
     * filter by. Looked up by slug once a day.
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function projectId(): int
    {
        return (int) Cache::remember('sentry:project-id:'.config('services.sentry_api.project'), now()->addDay(), fn (): int => (int) $this->client()
            ->get('/projects/'.$this->org().'/'.$this->projectSlug().'/')
            ->throw()
            ->json('id'));
    }

    /**
     * The environments the project has seen events in.
     *
     * @return list<string>
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function environments(): array
    {
        /** @var list<array{name: string}> $environments */
        $environments = $this->client()
            ->get('/projects/'.$this->org().'/'.$this->projectSlug().'/environments/', ['visibility' => 'visible'])
            ->throw()
            ->json();

        return array_map(fn (array $environment): string => $environment['name'], $environments);
    }

    /**
     * One page of issues matching the query, with the total match count
     * (Sentry's X-Hits header) and the cursors either side of the page.
     *
     * @param  array{query: string, sort: string, statsPeriod: string, environment: ?string, cursor: ?string, limit: int}  $filters
     * @return array{issues: list<array<string, mixed>>, total: int, next: ?string, previous: ?string}
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function issues(array $filters): array
    {
        $response = $this->client()
            ->get('/organizations/'.$this->org().'/issues/', array_filter([
                'project' => $this->projectId(),
                'query' => $filters['query'],
                'sort' => $filters['sort'],
                'statsPeriod' => $filters['statsPeriod'],
                'groupStatsPeriod' => 'auto',
                'environment' => $filters['environment'],
                'cursor' => $filters['cursor'],
                'limit' => $filters['limit'],
                'shortIdLookup' => 0,
            ], fn (mixed $value): bool => $value !== null && $value !== ''))
            ->throw();

        /** @var list<array<string, mixed>> $issues */
        $issues = $response->json();

        return [
            'issues' => $issues,
            'total' => (int) ($response->header('X-Hits') ?: count($issues)),
            'next' => $this->cursor($response, 'next'),
            'previous' => $this->cursor($response, 'previous'),
        ];
    }

    /**
     * How many issues match the query, without fetching more than one.
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function issueCount(string $query, string $statsPeriod, ?string $environment): int
    {
        return $this->issues([
            'query' => $query,
            'sort' => 'date',
            'statsPeriod' => $statsPeriod,
            'environment' => $environment,
            'cursor' => null,
            'limit' => 1,
        ])['total'];
    }

    /**
     * The issue, its latest event and its tag breakdown, fetched together.
     *
     * @return array{issue: array<string, mixed>, event: ?array<string, mixed>, tags: list<array<string, mixed>>}|null
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function issue(int $issueId): ?array
    {
        $base = '/organizations/'.$this->org().'/issues/'.$issueId;

        $responses = Http::pool(fn (Pool $pool): array => [
            $this->client($pool->as('issue'))->get($base.'/'),
            $this->client($pool->as('event'))->get($base.'/events/latest/'),
            $this->client($pool->as('tags'))->get($base.'/tags/', ['limit' => 5]),
        ]);

        $issue = $this->pooled($responses['issue']);

        if ($issue->notFound()) {
            return null;
        }

        $event = $this->pooled($responses['event']);
        $tags = $this->pooled($responses['tags']);

        return [
            'issue' => $issue->throw()->json(),
            'event' => $event->successful() ? $event->json() : null,
            'tags' => $tags->successful() ? $tags->json() : [],
        ];
    }

    /**
     * Error outcomes between the two moments, summed and bucketed by
     * interval: accepted, filtered, rate_limited, invalid, client_discard and
     * the rest, keyed by outcome. Outcomes carry no environment.
     *
     * @return array{intervals: list<string>, outcomes: array<string, array{total: int, series: list<int>}>}
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function outcomes(CarbonImmutable $start, CarbonImmutable $end, string $interval): array
    {
        $json = $this->client()
            ->get('/organizations/'.$this->org().'/stats_v2/', [
                'field' => 'sum(quantity)',
                'groupBy' => 'outcome',
                'category' => 'error',
                'project' => $this->projectId(),
                'start' => $start->utc()->toIso8601ZuluString(),
                'end' => $end->utc()->toIso8601ZuluString(),
                'interval' => $interval,
            ])
            ->throw()
            ->json();

        $outcomes = [];

        foreach ($json['groups'] ?? [] as $group) {
            $outcomes[(string) $group['by']['outcome']] = [
                'total' => (int) ($group['totals']['sum(quantity)'] ?? 0),
                'series' => array_values(array_map(intval(...), $group['series']['sum(quantity)'] ?? [])),
            ];
        }

        return [
            'intervals' => array_values($json['intervals'] ?? []),
            'outcomes' => $outcomes,
        ];
    }

    /**
     * Distinct users who hit an error in the period, or null when the plan's
     * events API refuses the query.
     *
     * @throws ConnectionException
     */
    public function usersAffected(string $statsPeriod, ?string $environment): ?int
    {
        $response = $this->client()
            ->get('/organizations/'.$this->org().'/events/', array_filter([
                'field' => 'count_unique(user)',
                'dataset' => 'errors',
                'project' => $this->projectId(),
                'statsPeriod' => $statsPeriod,
                'environment' => $environment,
                'referrer' => 'api.dashboard.errors',
            ]));

        if ($response->failed()) {
            return null;
        }

        $count = $response->json('data.0.count_unique(user)');

        return is_numeric($count) ? (int) $count : null;
    }

    /**
     * The project's most recent releases, newest first.
     *
     * @return list<array<string, mixed>>
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function releases(int $limit): array
    {
        /** @var list<array<string, mixed>> $releases */
        $releases = $this->client()
            ->get('/organizations/'.$this->org().'/releases/', [
                'project' => $this->projectId(),
                'per_page' => $limit,
                'sort' => 'date',
            ])
            ->throw()
            ->json();

        return $releases;
    }

    /**
     * Each release's commits and deploys, fetched together and keyed by
     * version. A release whose lookups fail gets empty lists.
     *
     * @param  list<string>  $versions
     * @return array<string, array{commits: list<array<string, mixed>>, deploys: list<array<string, mixed>>}>
     */
    public function releaseDetails(array $versions): array
    {
        if ($versions === []) {
            return [];
        }

        $base = '/organizations/'.$this->org().'/releases/';

        $responses = Http::pool(function (Pool $pool) use ($versions, $base): array {
            $requests = [];

            foreach ($versions as $index => $version) {
                $path = $base.rawurlencode($version);
                $requests[] = $this->client($pool->as('commits.'.$index))->get($path.'/commits/');
                $requests[] = $this->client($pool->as('deploys.'.$index))->get($path.'/deploys/');
            }

            return $requests;
        });

        $details = [];

        foreach ($versions as $index => $version) {
            $commits = $responses['commits.'.$index];
            $deploys = $responses['deploys.'.$index];

            $details[$version] = [
                'commits' => $commits instanceof Response && $commits->successful() ? $commits->json() : [],
                'deploys' => $deploys instanceof Response && $deploys->successful() ? $deploys->json() : [],
            ];
        }

        return $details;
    }

    /**
     * Where the project's issues and releases live in Sentry's own UI.
     */
    public function webUrl(string $path = ''): string
    {
        return $this->apiHost().'/organizations/'.$this->org().'/'.mb_ltrim($path, '/');
    }

    /**
     * The configured Sentry host, such as https://us.sentry.io, with any
     * trailing /api/0 dropped, since the API path is added per request.
     */
    private function apiHost(): string
    {
        return (string) preg_replace('#/api/0/?$#', '', mb_rtrim((string) config('services.sentry_api.url'), '/'));
    }

    private function client(?PendingRequest $request = null): PendingRequest
    {
        return ($request ?? Http::acceptJson())
            ->baseUrl($this->apiHost().'/api/0')
            ->withToken((string) config('services.sentry_api.token'))
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(10);
    }

    /**
     * A pooled request's response; a failure inside the pool comes back as
     * the exception rather than throwing.
     *
     * @throws ConnectionException
     */
    private function pooled(mixed $result): Response
    {
        throw_if($result instanceof ConnectionException, $result);

        throw_unless($result instanceof Response, ConnectionException::class, 'The Sentry request did not complete.', previous: $result instanceof Throwable ? $result : null);

        return $result;
    }

    /**
     * The cursor for the page on the given side, from Sentry's Link header,
     * or null when there are no results that way.
     */
    private function cursor(Response $response, string $side): ?string
    {
        foreach (explode(',', $response->header('Link')) as $link) {
            if (str_contains($link, 'rel="'.$side.'"')
                && str_contains($link, 'results="true"')
                && preg_match('/cursor="([^"]+)"/', $link, $match) === 1) {
                return $match[1];
            }
        }

        return null;
    }

    private function org(): string
    {
        return rawurlencode((string) config('services.sentry_api.org'));
    }

    private function projectSlug(): string
    {
        return rawurlencode((string) config('services.sentry_api.project'));
    }
}
