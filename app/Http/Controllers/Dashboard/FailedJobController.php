<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\DeleteFailedJobAction;
use App\Actions\RetryAllFailedJobsAction;
use App\Actions\RetryFailedJobAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\FailedJobRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Retries and deletes the failed jobs Horizon keeps. A job that is already
 * gone (trimmed, or deleted in another tab) is reported, not a 404.
 */
final class FailedJobController extends Controller
{
    public function retry(
        #[CurrentUser] User $user,
        string $jobId,
        FailedJobRequest $request,
        RetryFailedJobAction $retry,
    ): RedirectResponse {
        $this->toast($retry->handle($jobId, $user), 'The job was queued again.');

        return back();
    }

    public function retryAll(
        #[CurrentUser] User $user,
        FailedJobRequest $request,
        RetryAllFailedJobsAction $retryAll,
    ): RedirectResponse {
        $retried = $retryAll->handle($user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $retried === 0
                ? 'There are no failed jobs left to retry.'
                : sprintf('%d failed %s queued again.', $retried, $retried === 1 ? 'job was' : 'jobs were'),
        ]);

        return back();
    }

    public function destroy(
        #[CurrentUser] User $user,
        string $jobId,
        FailedJobRequest $request,
        DeleteFailedJobAction $delete,
    ): RedirectResponse {
        $this->toast($delete->handle($jobId, $user), 'The failed job was deleted.');

        return back();
    }

    private function toast(bool $found, string $message): void
    {
        Inertia::flash('toast', $found
            ? ['type' => 'success', 'message' => $message]
            : ['type' => 'error', 'message' => 'That failed job no longer exists.']);
    }
}
