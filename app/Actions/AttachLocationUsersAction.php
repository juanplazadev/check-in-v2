<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Attaches staff to a location, so they see its check-ins and appointments on
 * the dashboard. Someone already attached is left alone, and only the newly
 * attached are logged, on both the location's timeline and the acting staff
 * member's trail.
 */
final readonly class AttachLocationUsersAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @param  iterable<User>  $users
     * @return list<User> The users who were not attached before.
     *
     * @throws Throwable
     */
    public function handle(Location $location, iterable $users, User $actor): array
    {
        return DB::transaction(function () use ($location, $users, $actor): array {
            $byId = collect($users)->keyBy('id');

            $attachedIds = $location->users()->syncWithoutDetaching($byId->keys()->all())['attached'];

            $attached = array_values(array_map(fn (int|string $id): User => $byId->get((int) $id), $attachedIds));

            foreach ($attached as $user) {
                $this->recordHistory->handle($location, RecordHistoryEvent::LocationUserAttached, user: $actor, context: [
                    'user' => $user->uuid,
                    'name' => $user->name,
                ]);

                $this->recordUserHistory->handle($actor, UserHistoryEvent::LocationUserAttached, [
                    'location' => $location->uuid,
                    'user' => $user->uuid,
                ]);
            }

            return $attached;
        });
    }
}
