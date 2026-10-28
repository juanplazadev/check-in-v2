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
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read int $id
 * @property-read string $uuid
 * @property-read string $reference_number
 * @property-read int $location_id
 * @property-read int|null $user_id
 * @property-read int|null $dock_id
 * @property-read CarbonImmutable|null $dock_assigned_at
 * @property-read int|null $dock_assigned_by
 * @property-read CarbonImmutable $scheduled_for
 * @property-read string $drivers_name
 * @property-read string $drivers_cellphone
 * @property-read string $locale
 * @property-read AppointmentStatus $status
 * @property-read CarbonImmutable|null $cancelled_at
 * @property-read int|null $cancelled_by
 * @property-read string|null $cancelled_reason
 * @property-read CarbonImmutable|null $claimed_at
 * @property-read string|null $claimed_via
 * @property-read CarbonImmutable|null $reminder_sent_at
 * @property-read CarbonImmutable $created_at
 * @property-read CarbonImmutable $updated_at
 * @property-read CarbonImmutable|null $deleted_at
 * @property-read Location $location
 * @property-read User|null $user
 * @property-read Dock|null $dock
 * @property-read CheckIn|null $checkIn
 * @property-read Collection<int, AppointmentNote> $notes
 */
#[Fillable(['uuid', 'reference_number', 'location_id', 'user_id', 'dock_id', 'dock_assigned_at', 'dock_assigned_by', 'scheduled_for', 'drivers_name', 'drivers_cellphone', 'locale', 'status', 'cancelled_at', 'cancelled_by', 'cancelled_reason', 'claimed_at', 'claimed_via', 'reminder_sent_at'])]
#[Hidden(['id'])]
#[RouteKey('uuid')]
final class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    use HasShortLink;
    use MatchesSearchTerm;
    use SoftDeletes;
    use VisibleOnDashboard;

    /**
     * Includes a soft-deleted location, so past records keep showing where
     * they happened.
     *
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The dock staff placed this booking on; null until assigned.
     *
     * @return BelongsTo<Dock, $this>
     */
    public function dock(): BelongsTo
    {
        return $this->belongsTo(Dock::class);
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

    /**
     * Internal staff notes, newest first at the relation so the notes card is
     * one eager load with no sorting at the call site.
     *
     * @return HasMany<AppointmentNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(AppointmentNote::class)->latest()->latest('id');
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
            'dock_assigned_at' => 'datetime',
        ];
    }
}
