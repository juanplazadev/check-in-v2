<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserHistoryEvent;
use App\Models\User;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Jobs\RetryFailedJob;

/**
 * Pushes a copy of a failed job back onto its queue, the same way Horizon's
 * retry button does, and logs it on the acting admin's trail. RetryFailedJob
 * is not a queued job, so this runs inline and works while the workers are
 * stuck. The failed record stays, marked as retried.
 */
final readonly class RetryFailedJobAction
{
    public function __construct(
        private JobRepository $jobs,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @return bool Whether the failed job still existed.
     */
    public function handle(string $id, User $actor): bool
    {
        $job = $this->jobs->findFailed($id);

        if ($job === null) {
            return false;
        }

        dispatch_sync(new RetryFailedJob($id));

        $this->recordUserHistory->handle($actor, UserHistoryEvent::FailedJobRetried, [
            'job' => (string) $job->name,
        ]);

        return true;
    }
}
