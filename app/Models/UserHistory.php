<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserHistoryEvent;
use Carbon\CarbonImmutable;
use Database\Factories\UserHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An append-only trail of what a user does with their account: changes to
 * the account itself, and staff actions such as leaving a check-in note.
 *
 * @property-read int $id
 * @property-read int $user_id
 * @property-read UserHistoryEvent $event
 * @property-read array<string, mixed>|null $changes
 * @property-read CarbonImmutable $created_at
 * @property-read CarbonImmutable $updated_at
 * @property-read User $user
 */
#[Table('user_history')]
#[Fillable(['user_id', 'event', 'changes'])]
#[Hidden(['id'])]
final class UserHistory extends Model
{
    /** @use HasFactory<UserHistoryFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => UserHistoryEvent::class,
            'changes' => 'array',
        ];
    }
}
