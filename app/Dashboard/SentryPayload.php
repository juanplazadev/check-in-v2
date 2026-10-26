<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Services\SentryService;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

/**
 * Wraps a Sentry-backed prop in the state the page renders: ready with its
 * data, unconfigured when the API credentials are missing, missing when the
 * record is gone, or unavailable when Sentry cannot be reached. A Sentry
 * outage never fails the page.
 */
final readonly class SentryPayload
{
    public function __construct(private SentryService $sentry) {}

    /**
     * @template TData
     *
     * @param  Closure(): (TData|null)  $fetch
     * @return array{state: 'ready', data: TData}|array{state: 'unconfigured'|'missing'|'unavailable'}
     */
    public function resolve(Closure $fetch): array
    {
        if (! $this->sentry->isConfigured()) {
            return ['state' => 'unconfigured'];
        }

        try {
            $data = $fetch();
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            return ['state' => 'unavailable'];
        }

        return $data === null ? ['state' => 'missing'] : ['state' => 'ready', 'data' => $data];
    }
}
