<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Models\CheckIn;
use App\Models\Dock;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Takes a check-in off its door, freeing it for another truck. The driver is
 * not texted. A check-in with no door is left alone and nothing is logged.
 */
final readonly class UnassignCheckInDockAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(CheckIn $checkIn, User $user): void
    {
        DB::transaction(function () use ($checkIn, $user): void {
            $checkIn = CheckIn::query()->lockForUpdate()->with('dock.building')->findOrFail($checkIn->id);

            if (! $checkIn->dock instanceof Dock) {
                return;
            }

            $from = $checkIn->dock->label();

            $checkIn->forceFill([
                'dock_id' => null,
                'dock_assigned_at' => null,
                'dock_assigned_by' => null,
            ])->save();

            $this->recordHistory->handle($checkIn, RecordHistoryEvent::DockUnassigned, user: $user, context: ['from' => $from]);

            $this->recordUserHistory->handle($user, UserHistoryEvent::CheckInDockAssigned, [
                'check_in' => $checkIn->uuid,
                'from' => $from,
            ]);
        });
    }
}
