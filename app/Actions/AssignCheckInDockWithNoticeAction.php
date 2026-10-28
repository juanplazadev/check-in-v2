<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Sends a truck to a door and, when staff ask for it, texts the driver.
 * The text goes out only after the door is saved, so a rolled-back
 * assignment never reaches the driver's phone, and only when the door
 * actually changed, so a repeated save does not text them twice.
 */
final readonly class AssignCheckInDockWithNoticeAction
{
    public function __construct(
        private AssignCheckInDockAction $assign,
        private SendDoorAssignmentAction $sendDoorAssignment,
    ) {}

    /**
     * @throws ValidationException When the door cannot take this check-in.
     * @throws Throwable
     */
    public function handle(CheckIn $checkIn, User $user, string $dockUuid, bool $notify): void
    {
        $changed = DB::transaction(fn (): bool => $this->assign->handle($checkIn, $user, $dockUuid));

        if ($changed && $notify) {
            $this->sendDoorAssignment->handle($checkIn->refresh());
        }
    }
}
