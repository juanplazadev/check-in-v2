<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Decrypts a check-in's driver's license number for a staff member and logs
 * the view on both trails: the check-in's timeline, with the staff member as
 * actor, and the staff member's own account trail. The number itself stays out
 * of both trails, which only record that it was seen.
 */
final readonly class RevealCheckInLicenseAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(CheckIn $checkIn, User $viewer): string
    {
        return DB::transaction(function () use ($checkIn, $viewer): string {
            $this->recordHistory->handle($checkIn, RecordHistoryEvent::LicenseViewed, user: $viewer);

            $this->recordUserHistory->handle($viewer, UserHistoryEvent::CheckInLicenseViewed, [
                'check_in' => $checkIn->uuid,
            ]);

            return $checkIn->drivers_license_number;
        });
    }
}
