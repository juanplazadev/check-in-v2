<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Models\Dock;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Removes a dock that was added by mistake. A dock that ever held an
 * appointment or a check-in, archived ones included, is kept so those
 * records still name their door; staff turn it off instead.
 */
final readonly class DeleteDockAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @throws ValidationException When the dock has ever held an appointment or a check-in.
     * @throws Throwable
     */
    public function handle(Dock $dock, User $user): void
    {
        DB::transaction(function () use ($dock, $user): void {
            // Locked so an appointment cannot be placed on the dock while it is being removed.
            $location = Location::query()->lockForUpdate()->findOrFail($dock->location_id);

            if ($dock->appointments()->exists() || $dock->checkIns()->exists()) {
                throw ValidationException::withMessages([
                    'dock' => 'This dock has appointments or check-ins on record, so it cannot be removed. Turn it off instead.',
                ]);
            }

            $label = $dock->label();
            $dock->delete();

            $this->recordHistory->handle($location, RecordHistoryEvent::DockRemoved, user: $user, context: ['dock' => $label]);

            $this->recordUserHistory->handle($user, UserHistoryEvent::LocationDocksEdited, [
                'location' => $location->uuid,
                'dock' => $label,
            ]);
        });
    }
}
