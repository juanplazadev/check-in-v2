<?php

declare(strict_types=1);

namespace App\Dashboard;

use Closure;
use RedisException;

/**
 * Wraps a Horizon-backed prop in the state the page renders: ready with its
 * data, or unavailable when Redis cannot be reached. A Redis outage never
 * fails the page.
 */
final readonly class HorizonPayload
{
    /**
     * @template TData
     *
     * @param  Closure(): TData  $fetch
     * @return array{state: 'ready', data: TData}|array{state: 'unavailable'}
     */
    public function resolve(Closure $fetch): array
    {
        try {
            return ['state' => 'ready', 'data' => $fetch()];
        } catch (RedisException $redisException) {
            report($redisException);

            return ['state' => 'unavailable'];
        }
    }
}
