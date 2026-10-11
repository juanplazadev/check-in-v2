<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Encrypted;
use App\Dashboard\MatchesSearchTerm;
use App\Dashboard\VisibleOnDashboard;
use App\Encryption\BlindIndex;
use App\Enums\AppointmentLinkMethod;
use App\Enums\CheckInErpStatus;
use App\Enums\CheckInStatus;
use App\Enums\ErpSyncMethod;
use App\Enums\TrailerChute;
use App\ShortLink\HasShortLink;
use Carbon\CarbonImmutable;
use Database\Factories\CheckInFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read int $id
 * @property-read string $uuid
 * @property-read string $reference_number
 * @property-read int $location_id
 * @property-read int|null $user_id
 * @property-read int|null $appointment_id
 * @property-read CarbonImmutable|null $appointment_linked_at
 * @property-read AppointmentLinkMethod|null $appointment_linked_via
 * @property-read int|null $appointment_linked_by
 * @property-read CheckInStatus $status
 * @property-read CheckInErpStatus $erp_status
 * @property-read CarbonImmutable|null $completed_at
 * @property-read int|null $completed_by
 * @property-read CarbonImmutable|null $erp_synced_at
 * @property-read ErpSyncMethod|null $erp_synced_via
 * @property-read int|null $erp_synced_by
 * @property-read string $customer
 * @property-read string $destination_city
 * @property-read string $destination_state
 * @property-read string $destination_country
 * @property-read string $truck_name
 * @property-read string $truck_plate
 * @property-read string|null $truck_color
 * @property-read string|null $truck_plate_state
 * @property-read string|null $truck_plate_country
 * @property-read string $trailer_plate
 * @property-read string|null $trailer_plate_state
 * @property-read string|null $trailer_plate_country
 * @property-read TrailerChute|null $trailer_chute
 * @property-read string|null $empty_weight_lbs
 * @property-read string $drivers_name
 * @property-read string $drivers_cellphone
 * @property-read string|null $drivers_email
 * @property-read string $drivers_license_number
 * @property-read string|null $drivers_license_hash
 * @property-read string|null $drivers_license_expiration_date
 * @property-read string|null $drivers_license_state
 * @property-read string|null $drivers_license_country
 * @property-read string|null $loading_instructions
 * @property-read string $locale
 * @property-read CarbonImmutable|null $claimed_at
 * @property-read string|null $claimed_via
 * @property-read CarbonImmutable $created_at
 * @property-read CarbonImmutable $updated_at
 * @property-read CarbonImmutable|null $deleted_at
 * @property-read Location $location
 * @property-read User|null $user
 * @property-read Appointment|null $appointment
 * @property-read User|null $appointmentLinkedBy
 * @property-read User|null $completedBy
 * @property-read User|null $erpSyncedBy
 * @property-read Collection<int, CheckInNote> $notes
 */
#[Fillable(['uuid', 'reference_number', 'location_id', 'user_id', 'appointment_id',
    'appointment_linked_at', 'appointment_linked_via', 'appointment_linked_by', 'status', 'erp_status',
    'completed_at', 'completed_by', 'erp_synced_at', 'erp_synced_via', 'erp_synced_by', 'customer',
    'destination_city', 'destination_state', 'destination_country', 'truck_name', 'truck_plate', 'truck_color',
    'truck_plate_state', 'truck_plate_country', 'trailer_plate', 'trailer_plate_state', 'trailer_plate_country',
    'trailer_chute', 'empty_weight_lbs', 'drivers_name', 'drivers_cellphone', 'drivers_email', 'drivers_license_number',
    'drivers_license_expiration_date', 'drivers_license_state', 'drivers_license_country', 'loading_instructions',
    'locale', 'claimed_at', 'claimed_via'])
]
#[Hidden(['id', 'drivers_license_hash'])]
#[RouteKey('uuid')]
final class CheckIn extends Model
{
    /** @use HasFactory<CheckInFactory> */
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
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Whoever tied the check-in to its appointment: the staff member for a
     * staff link, or the signed-in driver for a conversion. Null for a guest
     * conversion.
     *
     * @return BelongsTo<User, $this>
     */
    public function appointmentLinkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'appointment_linked_by');
    }

    /**
     * The staff member who marked the check-in completed.
     *
     * @return BelongsTo<User, $this>
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * The staff member who pushed the check-in to the ERP. Null for an
     * automatic sync.
     *
     * @return BelongsTo<User, $this>
     */
    public function erpSyncedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'erp_synced_by');
    }

    /**
     * @return MorphMany<PurchaseOrder, $this>
     */
    public function purchaseOrders(): MorphMany
    {
        return $this->morphMany(PurchaseOrder::class, 'purchasable');
    }

    /**
     * The append-only trail of what happened to this check-in.
     *
     * Ordered oldest-first at the relation, so a timeline is one eager load
     * with no sorting at the call site.
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
     * @return HasMany<CheckInNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(CheckInNote::class)->latest()->latest('id');
    }

    /**
     * Keep the license blind index in step with the encrypted number, on every
     * write path, so search never misses a check-in. Like any model event this
     * misses mass Query::update().
     */
    protected static function booted(): void
    {
        self::saving(static function (CheckIn $checkIn): void {
            if ($checkIn->isDirty('drivers_license_number')) {
                $checkIn->setAttribute('drivers_license_hash', resolve(BlindIndex::class)->hash($checkIn->drivers_license_number));
            }
        });
    }

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'status' => CheckInStatus::class,
            'erp_status' => CheckInErpStatus::class,
            'appointment_linked_at' => 'datetime',
            'appointment_linked_via' => AppointmentLinkMethod::class,
            'completed_at' => 'datetime',
            'erp_synced_at' => 'datetime',
            'erp_synced_via' => ErpSyncMethod::class,
            'trailer_chute' => TrailerChute::class,
            'drivers_license_number' => Encrypted::class,
            'claimed_at' => 'datetime',
        ];
    }
}
