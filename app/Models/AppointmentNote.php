<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\AppointmentNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An internal staff note on an appointment. Add-only: notes are never edited
 * or removed, so the card reads as a log. Drivers never see them.
 *
 * @property-read int $id
 * @property-read int $appointment_id
 * @property-read int|null $user_id
 * @property-read string $body
 * @property-read CarbonImmutable $created_at
 * @property-read CarbonImmutable $updated_at
 * @property-read Appointment $appointment
 * @property-read User|null $user
 */
#[Table('appointment_notes')]
#[Fillable(['appointment_id', 'user_id', 'body'])]
final class AppointmentNote extends Model
{
    /** @use HasFactory<AppointmentNoteFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
