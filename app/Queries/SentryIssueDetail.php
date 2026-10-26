<?php

declare(strict_types=1);

namespace App\Queries;

use App\Services\SentryService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;

/**
 * One issue for the error detail page: its summary and 30-day trend, the
 * latest event's exception chain, breadcrumbs, request, user and runtime
 * context, and how its events split across the top tags. Cached for a minute.
 *
 * Stack frames are listed most recent call first, as they read in a PHP
 * trace; Sentry sends them oldest first.
 */
final readonly class SentryIssueDetail
{
    private const int BREADCRUMB_LIMIT = 30;

    public function __construct(private SentryService $sentry) {}

    /**
     * @return array{issue: array<string, mixed>, event: ?array<string, mixed>, tags: list<array<string, mixed>>}|null
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function execute(int $issueId): ?array
    {
        $cached = Cache::remember('sentry:issue:'.$issueId, now()->addMinute(), function () use ($issueId): array {
            $result = $this->sentry->issue($issueId);

            return $result === null ? ['found' => false] : [
                'found' => true,
                'issue' => $this->issue($result['issue']),
                'event' => $result['event'] === null ? null : $this->event($result['event']),
                'tags' => $this->tags($result['tags']),
            ];
        });

        if (! $cached['found']) {
            return null;
        }

        unset($cached['found']);

        return $cached;
    }

    /**
     * @param  array<string, mixed>  $issue
     * @return array<string, mixed>
     */
    private function issue(array $issue): array
    {
        /** @var list<array{0: int, 1: int}> $trend */
        $trend = $issue['stats']['30d'] ?? $issue['stats']['24h'] ?? [];

        return [
            'id' => (string) $issue['id'],
            'shortId' => (string) ($issue['shortId'] ?? ''),
            'title' => (string) ($issue['title'] ?? ''),
            'culprit' => (string) ($issue['culprit'] ?? ''),
            'type' => $issue['metadata']['type'] ?? null,
            'value' => $issue['metadata']['value'] ?? null,
            'level' => (string) ($issue['level'] ?? 'error'),
            'status' => (string) ($issue['status'] ?? 'unresolved'),
            'substatus' => $issue['substatus'] ?? null,
            'isUnhandled' => (bool) ($issue['isUnhandled'] ?? false),
            'platform' => $issue['platform'] ?? null,
            'events' => (int) ($issue['count'] ?? 0),
            'users' => (int) ($issue['userCount'] ?? 0),
            'firstSeen' => $issue['firstSeen'] ?? null,
            'lastSeen' => $issue['lastSeen'] ?? null,
            'firstRelease' => $issue['firstRelease']['version'] ?? null,
            'lastRelease' => $issue['lastRelease']['version'] ?? null,
            'permalink' => $issue['permalink'] ?? null,
            'trend' => [
                'labels' => array_map(fn (array $point): int => (int) $point[0] * 1000, $trend),
                'counts' => array_map(fn (array $point): int => (int) $point[1], $trend),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function event(array $event): array
    {
        $entries = [];

        foreach ($event['entries'] ?? [] as $entry) {
            $entries[$entry['type']] = $entry['data'] ?? [];
        }

        $tags = [];

        foreach ($event['tags'] ?? [] as $tag) {
            $tags[(string) $tag['key']] = (string) $tag['value'];
        }

        $user = $event['user'] ?? null;

        return [
            'id' => (string) ($event['eventID'] ?? $event['id'] ?? ''),
            'dateCreated' => $event['dateCreated'] ?? null,
            'message' => $event['message'] ?? null,
            'environment' => $tags['environment'] ?? null,
            'release' => $event['release']['version'] ?? $tags['release'] ?? null,
            'exceptions' => $this->exceptions($entries['exception']['values'] ?? []),
            'breadcrumbs' => $this->breadcrumbs($entries['breadcrumbs']['values'] ?? []),
            'request' => isset($entries['request']['url']) ? [
                'method' => $entries['request']['method'] ?? null,
                'url' => (string) $entries['request']['url'],
            ] : null,
            'user' => is_array($user) && array_filter($user) !== [] ? [
                'id' => $user['id'] ?? null,
                'email' => $user['email'] ?? null,
                'username' => $user['username'] ?? null,
                'ip' => $user['ip_address'] ?? null,
            ] : null,
            'contexts' => $this->contexts($event['contexts'] ?? []),
            'tags' => array_map(
                fn (string $key, string $value): array => ['key' => $key, 'value' => $value],
                array_keys($tags),
                array_values($tags),
            ),
        ];
    }

    /**
     * The exception chain, outermost first, each with its frames most recent
     * call first.
     *
     * @param  list<array<string, mixed>>  $values
     * @return list<array<string, mixed>>
     */
    private function exceptions(array $values): array
    {
        return array_map(fn (array $exception): array => [
            'type' => (string) ($exception['type'] ?? 'Error'),
            'value' => (string) ($exception['value'] ?? ''),
            'handled' => $exception['mechanism']['handled'] ?? null,
            'mechanism' => $exception['mechanism']['type'] ?? null,
            'frames' => array_values(array_map(fn (array $frame): array => [
                'filename' => (string) ($frame['filename'] ?? $frame['absPath'] ?? $frame['module'] ?? '?'),
                'function' => $frame['function'] ?? null,
                'lineNo' => $frame['lineNo'] ?? null,
                'colNo' => $frame['colNo'] ?? null,
                'inApp' => (bool) ($frame['inApp'] ?? false),
                'context' => array_values(array_map(
                    fn (array $line): array => ['line' => (int) $line[0], 'code' => (string) $line[1]],
                    $frame['context'] ?? [],
                )),
            ], array_reverse($exception['stacktrace']['frames'] ?? []))),
        ], array_reverse($values));
    }

    /**
     * The breadcrumbs leading up to the event, most recent first.
     *
     * @param  list<array<string, mixed>>  $values
     * @return list<array{timestamp: ?string, category: ?string, level: string, type: string, message: ?string}>
     */
    private function breadcrumbs(array $values): array
    {
        return array_map(fn (array $crumb): array => [
            'timestamp' => $crumb['timestamp'] ?? null,
            'category' => $crumb['category'] ?? null,
            'level' => (string) ($crumb['level'] ?? 'info'),
            'type' => (string) ($crumb['type'] ?? 'default'),
            'message' => $crumb['message'] ?? (isset($crumb['data']) ? (json_encode($crumb['data'], JSON_UNESCAPED_SLASHES) ?: null) : null),
        ], array_slice(array_reverse($values), 0, self::BREADCRUMB_LIMIT));
    }

    /**
     * Runtime, OS, browser and device, as "PHP 8.5.1".
     *
     * @param  array<string, array<string, mixed>>  $contexts
     * @return list<array{key: string, value: string}>
     */
    private function contexts(array $contexts): array
    {
        $rows = [];

        foreach (['runtime', 'os', 'browser', 'device', 'client_os'] as $key) {
            $context = $contexts[$key] ?? null;

            if (! is_array($context) || ! isset($context['name'])) {
                continue;
            }

            $rows[] = ['key' => $key, 'value' => mb_trim($context['name'].' '.($context['version'] ?? ''))];
        }

        return $rows;
    }

    /**
     * Each tag's top values with their share of the issue's events.
     *
     * @param  list<array<string, mixed>>  $tags
     * @return list<array{key: string, name: string, total: int, top: list<array{value: string, count: int, percent: float}>}>
     */
    private function tags(array $tags): array
    {
        $wanted = ['environment', 'release', 'url', 'browser', 'os', 'transaction', 'server_name', 'level'];

        $rows = array_filter($tags, fn (array $tag): bool => in_array($tag['key'] ?? null, $wanted, true));

        usort($rows, fn (array $a, array $b): int => array_search($a['key'], $wanted, true) <=> array_search($b['key'], $wanted, true));

        return array_map(function (array $tag): array {
            $total = max(1, (int) ($tag['totalValues'] ?? 0));

            return [
                'key' => (string) $tag['key'],
                'name' => (string) ($tag['name'] ?? $tag['key']),
                'total' => (int) ($tag['totalValues'] ?? 0),
                'top' => array_values(array_map(fn (array $value): array => [
                    'value' => (string) ($value['value'] ?? $value['name'] ?? ''),
                    'count' => (int) ($value['count'] ?? 0),
                    'percent' => round((int) ($value['count'] ?? 0) / $total * 100, 1),
                ], array_slice($tag['topValues'] ?? [], 0, 5))),
            ];
        }, $rows);
    }
}
