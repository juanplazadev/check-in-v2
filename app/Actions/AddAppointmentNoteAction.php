<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Models\Appointment;
use App\Models\AppointmentNote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Stores a staff note on an appointment and logs it on both trails: the
 * appointment's timeline, with the author as actor, and the author's own
 * account trail. The note body stays out of both trails, which only record
 * that a note was left.
 */
final readonly class AddAppointmentNoteAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(Appointment $appointment, User $author, string $body): AppointmentNote
    {
        return DB::transaction(function () use ($appointment, $author, $body): AppointmentNote {
            $note = AppointmentNote::query()->create([
                'appointment_id' => $appointment->id,
                'user_id' => $author->id,
                'body' => $body,
            ]);

            $this->recordHistory->handle($appointment, RecordHistoryEvent::NoteAdded, user: $author);

            $this->recordUserHistory->handle($author, UserHistoryEvent::AppointmentNoteAdded, [
                'appointment' => $appointment->uuid,
            ]);

            return $note;
        });
    }
}
