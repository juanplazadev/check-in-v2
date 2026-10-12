<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Models\CheckIn;
use App\Models\CheckInNote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Stores a staff note on a check-in and logs it on both trails: the check-in's
 * timeline, with the author as actor, and the author's own account trail. The
 * note body stays out of both trails, which only record that a note was left.
 */
final readonly class AddCheckInNoteAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(CheckIn $checkIn, User $author, string $body): CheckInNote
    {
        return DB::transaction(function () use ($checkIn, $author, $body): CheckInNote {
            $note = CheckInNote::query()->create([
                'check_in_id' => $checkIn->id,
                'user_id' => $author->id,
                'body' => $body,
            ]);

            $this->recordHistory->handle($checkIn, RecordHistoryEvent::NoteAdded, user: $author);

            $this->recordUserHistory->handle($author, UserHistoryEvent::CheckInNoteAdded, [
                'check_in' => $checkIn->uuid,
            ]);

            return $note;
        });
    }
}
