<?php

declare(strict_types=1);

namespace App\Models;

use App\Dashboard\MatchesSearchTerm;
use App\Dashboard\VisibleOnDashboard;
use App\Enums\AppointmentStatus;
use App\ShortLink\HasShortLink;
use Carbon\CarbonImmutable;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read int $id
 * @property-read string $uuid
 * @property-read string $reference_number
 * @property-read int $location_id
 * @property-read int|null $user_id
 * @property-read CarbonImmutable $scheduled_for
 * @property-read string $drivers_name
 * @property-read string $drivers_cellphone
 * @property-read string $locale
 * @property-read AppointmentStatus $status
 * @property-read CarbonImmutable|null $cancelled_at
 * @property-read string|null $cancelled_reason
 * @property-read CarbonImmutable|null $claimed_at
 * @property-read string|null $claimed_via
 * @property-read CarbonImmutable|null $reminder_sent_at
 * @property-read CarbonImmutable $created_at
 * @property-read CarbonImmutable $updated_at
 * @property-read CarbonImmutable|null $deleted_at
 * @property-read Location $location
 * @property-read User|null $user
 * @property-read CheckIn|null $checkIn
 */
#[Fillable(['uuid', 'reference_number', 'location_id', 'user_id', 'scheduled_for', 'drivers_name', 'drivers_cellphone', 'locale', 'status', 'cancelled_at', 'cancelled_reason', 'claimed_at', 'claimed_via', 'reminder_sent_at'])]
#[Hidden(['id'])]
final class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    use HasShortLink;
    use MatchesSearchTerm;
    use SoftDeletes;
    use VisibleOnDashboard;

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphMany<PurchaseOrder, $this>
     */
    public function purchaseOrders(): MorphMany
    {
        return $this->morphMany(PurchaseOrder::class, 'purchasable');
    }

    /**
     * The check-in this booking turned into, once the driver arrives.
     *
     * @return HasOne<CheckIn, $this>
     */
    public function checkIn(): HasOne
    {
        return $this->hasOne(CheckIn::class);
    }

    /**
     * The append-only trail of what happened to this booking. See
     * {@see CheckIn::history()}.
     *
     * @return MorphMany<RecordHistory, $this>
     */
    public function history(): MorphMany
    {
        return $this->morphMany(RecordHistory::class, 'recordable')->oldest();
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'scheduled_for' => 'datetime',
            'status' => AppointmentStatus::class,
            'cancelled_at' => 'datetime',
            'claimed_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }
}
