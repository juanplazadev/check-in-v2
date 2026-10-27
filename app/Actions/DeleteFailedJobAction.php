<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserHistoryEvent;
use App\Models\User;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Laravel\Horizon\Contracts\JobRepository;

/**
 * Deletes a failed job from Horizon and from the failed_jobs table (both are
 * keyed by the job's uuid), and logs it on the acting admin's trail.
 */
final readonly class DeleteFailedJobAction
{
    public function __construct(
        private JobRepository $jobs,
        private FailedJobProviderInterface $failer,
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

        $this->jobs->deleteFailed($id);
        $this->failer->forget($id);

        $this->recordUserHistory->handle($actor, UserHistoryEvent::FailedJobDeleted, [
            'job' => (string) $job->name,
        ]);

        return true;
    }
}
