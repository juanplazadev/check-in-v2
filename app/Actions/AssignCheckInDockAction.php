<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CheckInStatus;
use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Models\CheckIn;
use App\Models\Dock;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Sends a waiting truck to a door, or moves it to another, and logs it on the
 * check-in's timeline and the staff member's trail. The save broadcasts the
 * change to open dashboards through the model's updated() hook.
 *
 * A door holds one pending check-in at a time. It frees itself once that
 * check-in is completed or canceled, so nothing has to clear it.
 */
final readonly class AssignCheckInDockAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @return bool Whether the door changed; false when it was already this one.
     *
     * @throws ValidationException When the check-in is not pending, or the dock is not open or is in use.
     * @throws Throwable
     */
    public function handle(CheckIn $checkIn, User $user, string $dockUuid): bool
    {
        return DB::transaction(function () use ($checkIn, $user, $dockUuid): bool {
            // Locked so two staff cannot send different trucks to the same door at once.
            Location::query()->lockForUpdate()->findOrFail($checkIn->location_id);

            $checkIn = CheckIn::query()->with('dock.building')->findOrFail($checkIn->id);

            if ($checkIn->status !== CheckInStatus::Pending) {
                throw ValidationException::withMessages(['dock' => 'Only a pending check-in can be sent to a door.']);
            }

            $dock = Dock::query()
                ->where('location_id', $checkIn->location_id)
                ->where('uuid', $dockUuid)
                ->where('is_active', true)
                ->with('building')
                ->first();

            if (! $dock instanceof Dock) {
                throw ValidationException::withMessages(['dock' => 'Choose an active door at this check-in\'s location.']);
            }

            if ($checkIn->dock_id === $dock->id) {
                return false;
            }

            $holder = CheckIn::query()
                ->where('dock_id', $dock->id)
                ->where('status', CheckInStatus::Pending)
                ->whereKeyNot($checkIn->id)
                ->value('reference_number');

            if ($holder !== null) {
                throw ValidationException::withMessages([
                    'dock' => sprintf('%s is in use by %s.', $dock->name, $holder),
                ]);
            }

            $from = $checkIn->dock?->label();

            $checkIn->forceFill([
                'dock_id' => $dock->id,
                'dock_assigned_at' => Date::now(),
                'dock_assigned_by' => $user->id,
            ])->save();

            $context = array_filter(['from' => $from, 'to' => $dock->label()], fn (?string $value): bool => $value !== null);

            $this->recordHistory->handle($checkIn, RecordHistoryEvent::DockAssigned, user: $user, context: $context);

            $this->recordUserHistory->handle($user, UserHistoryEvent::CheckInDockAssigned, [
                'check_in' => $checkIn->uuid,
                ...$context,
            ]);

            return true;
        });
    }
}
