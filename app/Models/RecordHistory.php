<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordHistoryEvent;
use Carbon\CarbonImmutable;
use Database\Factories\RecordHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * An append-only trail of what happened to a check-in, a booking or a
 * location: what was sent, on which channel, in which language, and what the
 * record did next, or which staff edit changed it.
 *
 * @property-read int $id
 * @property-read string $recordable_type
 * @property-read int $recordable_id
 * @property-read RecordHistoryEvent $event
 * @property-read string|null $subject
 * @property-read string|null $channel
 * @property-read string|null $locale
 * @property-read int|null $user_id
 * @property-read array<string, mixed>|null $context
 * @property-read CarbonImmutable $created_at
 * @property-read CarbonImmutable $updated_at
 * @property-read Model $recordable
 * @property-read User|null $user
 */
#[Table('record_history')]
#[Fillable(['recordable_type', 'recordable_id', 'event', 'subject', 'channel', 'locale', 'user_id', 'context'])]
#[Hidden(['id'])]
final class RecordHistory extends Model
{
    /** @use HasFactory<RecordHistoryFactory> */
    use HasFactory;

    /**
     * @return MorphTo<Model, $this>
     */
    public function recordable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => RecordHistoryEvent::class,
            'context' => 'array',
        ];
    }
}
