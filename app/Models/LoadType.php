<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LoadDirection;
use Carbon\CarbonImmutable;
use Database\Factories\LoadTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A kind of load a location takes, like "Live unload, floor loaded". Drivers
 * pick one when they book, and its duration decides how long the booking
 * holds a dock; see AppointmentSlotCapacity.
 *
 * @property-read int $id
 * @property-read string $uuid
 * @property-read int $location_id
 * @property-read string $name
 * @property-read LoadDirection $direction
 * @property-read int $duration_minutes
 * @property-read bool $is_active
 * @property-read int $sort_order
 * @property-read CarbonImmutable $created_at
 * @property-read CarbonImmutable $updated_at
 * @property-read Location $location
 * @property-read Collection<int, Appointment> $appointments
 */
#[Fillable(['uuid', 'location_id', 'name', 'direction', 'duration_minutes', 'is_active', 'sort_order'])]
#[Hidden(['id'])]
#[RouteKey('uuid')]
final class LoadType extends Model
{
    /** @use HasFactory<LoadTypeFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Includes archived appointments, so a load type that was ever booked is never deleted.
     *
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class)->withTrashed();
    }

    /**
     * Load types drivers can book.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'direction' => LoadDirection::class,
            'duration_minutes' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
