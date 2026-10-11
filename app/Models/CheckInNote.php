<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\CheckInNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An internal staff note on a check-in. Add-only: notes are never edited or
 * removed, so the card reads as a log. Drivers never see them.
 *
 * @property-read int $id
 * @property-read int $check_in_id
 * @property-read int|null $user_id
 * @property-read string $body
 * @property-read CarbonImmutable $created_at
 * @property-read CarbonImmutable $updated_at
 * @property-read CheckIn $checkIn
 * @property-read User|null $user
 */
#[Table('check_in_notes')]
#[Fillable(['check_in_id', 'user_id', 'body'])]
final class CheckInNote extends Model
{
    /** @use HasFactory<CheckInNoteFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<CheckIn, $this>
     */
    public function checkIn(): BelongsTo
    {
        return $this->belongsTo(CheckIn::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
