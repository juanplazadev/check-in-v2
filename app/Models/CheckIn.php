<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Encrypted;
use App\Dashboard\MatchesSearchTerm;
use App\Dashboard\VisibleOnDashboard;
use App\Encryption\BlindIndex;
use App\Enums\CheckInErpStatus;
use App\Enums\CheckInStatus;
use App\Enums\TrailerChute;
use App\ShortLink\HasShortLink;
use Carbon\CarbonImmutable;
use Database\Factories\CheckInFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read int $id
 * @property-read string $uuid
 * @property-read string $reference_number
 * @property-read int $location_id
 * @property-read int|null $user_id
 * @property-read int|null $appointment_id
 * @property-read CheckInStatus $status
 * @property-read CheckInErpStatus $erp_status
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
 */
#[Fillable(['uuid', 'reference_number', 'location_id', 'user_id', 'appointment_id', 'status', 'erp_status', 'customer',
    'destination_city', 'destination_state', 'destination_country', 'truck_name', 'truck_plate', 'truck_color',
    'truck_plate_state', 'truck_plate_country', 'trailer_plate', 'trailer_plate_state', 'trailer_plate_country',
    'trailer_chute', 'empty_weight_lbs', 'drivers_name', 'drivers_cellphone', 'drivers_email', 'drivers_license_number',
    'drivers_license_expiration_date', 'drivers_license_state', 'drivers_license_country', 'loading_instructions',
    'locale', 'claimed_at', 'claimed_via'])
]
#[Hidden(['id', 'drivers_license_hash'])]
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

    public function getRouteKeyName(): string
    {
        return 'uuid';
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
            'trailer_chute' => TrailerChute::class,
            'drivers_license_number' => Encrypted::class,
            'claimed_at' => 'datetime',
        ];
    }
}
