<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserHistoryEvent;
use App\Models\User;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Jobs\RetryFailedJob;
use stdClass;

/**
 * Retries every failed job that has not been retried yet, walking Horizon's
 * failed list 50 at a time, and logs one entry with the count.
 */
final readonly class RetryAllFailedJobsAction
{
    /** Horizon's fixed page size for job lists. */
    private const int PAGE_SIZE = 50;

    public function __construct(
        private JobRepository $jobs,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @return int How many jobs were retried.
     */
    public function handle(User $actor): int
    {
        $seen = [];
        $afterIndex = null;

        do {
            $page = $this->jobs->getFailed($afterIndex);

            foreach ($page as $job) {
                $id = (string) $job->id;

                // Pages can overlap when a job is trimmed mid-walk; retrying is async, so retried_by is not set yet.
                if (isset($seen[$id]) || ! $this->isUnretried($job)) {
                    continue;
                }

                $seen[$id] = true;
                dispatch(new RetryFailedJob($id));
            }

            $lastIndex = $page->keys()->last();
            $afterIndex = $lastIndex === null ? null : (string) $lastIndex;
        } while ($page->count() >= self::PAGE_SIZE);

        $retried = count($seen);

        if ($retried > 0) {
            $this->recordUserHistory->handle($actor, UserHistoryEvent::FailedJobsRetried, [
                'count' => $retried,
            ]);
        }

        return $retried;
    }

    private function isUnretried(stdClass $job): bool
    {
        return $job->retried_by === null || json_decode((string) $job->retried_by, true) === [];
    }
}
