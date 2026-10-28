<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\ErrorIssueSort;
use App\Enums\ErrorIssueTab;
use App\Enums\ErrorPeriod;
use App\Services\SentryService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;

/**
 * One page of the errors page's issue list, read from Sentry and cached for a
 * minute. The tab's search and the admin's own search are joined into one
 * Sentry query.
 */
final readonly class SentryIssues
{
    private const int PAGE_SIZE = 25;

    public function __construct(private SentryService $sentry) {}

    /**
     * @param  array{period: ErrorPeriod, tab: ErrorIssueTab, sort: ErrorIssueSort, environment: ?string, search: string, cursor: ?string}  $filters
     * @return array{
     *     rows: list<array{id: string, shortId: string, title: string, culprit: string, type: ?string, level: string, status: string, substatus: ?string, isUnhandled: bool, events: int, users: int, firstSeen: ?string, lastSeen: ?string, trend: list<int>, permalink: ?string}>,
     *     total: int,
     *     next: ?string,
     *     previous: ?string,
     *     query: string,
     * }
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function execute(array $filters): array
    {
        $query = mb_trim($filters['tab']->query($filters['period']).' '.$filters['search']);

        $page = [
            'query' => $query,
            'sort' => $filters['sort']->value,
            'statsPeriod' => $filters['period']->value,
            'environment' => $filters['environment'],
            'cursor' => $filters['cursor'],
            'limit' => self::PAGE_SIZE,
        ];

        return Cache::remember('sentry:issues:'.md5(serialize($page)), now()->addMinute(), function () use ($page, $query): array {
            $result = $this->sentry->issues($page);

            return [
                'rows' => array_map($this->row(...), $result['issues']),
                'total' => $result['total'],
                'next' => $result['next'],
                'previous' => $result['previous'],
                'query' => $query,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $issue
     * @return array{id: string, shortId: string, title: string, culprit: string, type: ?string, level: string, status: string, substatus: ?string, isUnhandled: bool, events: int, users: int, firstSeen: ?string, lastSeen: ?string, trend: list<int>, permalink: ?string}
     */
    private function row(array $issue): array
    {
        /** @var array<string, list<array{0: int, 1: int}>> $stats */
        $stats = $issue['stats'] ?? [];
        $trend = array_map(fn (array $point): int => (int) $point[1], array_first($stats) ?? []);

        return [
            'id' => (string) $issue['id'],
            'shortId' => (string) ($issue['shortId'] ?? ''),
            'title' => (string) ($issue['title'] ?? ''),
            'culprit' => (string) ($issue['culprit'] ?? ''),
            'type' => isset($issue['metadata']['type']) ? (string) $issue['metadata']['type'] : null,
            'level' => (string) ($issue['level'] ?? 'error'),
            'status' => (string) ($issue['status'] ?? 'unresolved'),
            'substatus' => isset($issue['substatus']) ? (string) $issue['substatus'] : null,
            'isUnhandled' => (bool) ($issue['isUnhandled'] ?? false),
            'events' => (int) ($issue['count'] ?? 0),
            'users' => (int) ($issue['userCount'] ?? 0),
            'firstSeen' => $issue['firstSeen'] ?? null,
            'lastSeen' => $issue['lastSeen'] ?? null,
            'trend' => $trend,
            'permalink' => $issue['permalink'] ?? null,
        ];
    }
}
