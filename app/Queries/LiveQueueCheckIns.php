<?php

declare(strict_types=1);

namespace App\Queries;

use App\Dashboard\CheckInRecordResolver;
use App\Enums\CheckInStatus;
use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Pending check-ins shipping still has to work at the user's locations,
 * oldest first.
 *
 * Bounded to a recent window: nothing completes check-ins in the app yet, so
 * without it every check-in ever submitted would sit in the queue forever.
 */
final readonly class LiveQueueCheckIns
{
    public const int WINDOW_HOURS = 24;

    /** A ceiling on the payload, far above any real day's check-ins. */
    public const int MAX_RECORDS = 200;

    /**
     * @return Collection<int, CheckIn>
     */
    public function execute(User $user): Collection
    {
        return $this->query($user)
            ->with(CheckInRecordResolver::RELATIONS)
            ->oldest()
            ->orderBy('id')
            ->limit(self::MAX_RECORDS)
            ->get();
    }

    public function count(User $user): int
    {
        return $this->query($user)->count();
    }

    /**
     * @return Builder<CheckIn>
     */
    private function query(User $user): Builder
    {
        return CheckIn::query()
            ->visibleTo($user)
            ->where('status', CheckInStatus::Pending)
            ->where('created_at', '>=', now()->subHours(self::WINDOW_HOURS));
    }
}
