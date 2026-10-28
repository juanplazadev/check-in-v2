<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DockFactory;
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
 * A dock door at a location. Every active dock that accepts appointments
 * adds one booking to each appointment slot; see AppointmentSlotCapacity.
 * Staff place a booked appointment on a dock from its dashboard page.
 *
 * @property-read int $id
 * @property-read string $uuid
 * @property-read int $location_id
 * @property-read int|null $building_id
 * @property-read string $name
 * @property-read bool $is_active
 * @property-read bool $accepts_appointments
 * @property-read int $sort_order
 * @property-read CarbonImmutable $created_at
 * @property-read CarbonImmutable $updated_at
 * @property-read Location $location
 * @property-read Building|null $building
 * @property-read Collection<int, Appointment> $appointments
 * @property-read Collection<int, CheckIn> $checkIns
 */
#[Fillable(['uuid', 'location_id', 'building_id', 'name', 'is_active', 'accepts_appointments', 'sort_order'])]
#[Hidden(['id'])]
#[RouteKey('uuid')]
final class Dock extends Model
{
    /** @use HasFactory<DockFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<Building, $this>
     */
    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    /**
     * Includes archived appointments, so a dock that ever held one is never deleted.
     *
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class)->withTrashed();
    }

    /**
     * Includes archived check-ins, for the same reason as appointments().
     *
     * @return HasMany<CheckIn, $this>
     */
    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class)->withTrashed();
    }

    /**
     * The name staff read, with the building when there is one: "Building B / Door 12".
     */
    public function label(): string
    {
        return $this->building instanceof Building
            ? sprintf('%s / %s', $this->building->name, $this->name)
            : $this->name;
    }

    /**
     * Docks that count toward appointment capacity.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function bookable(Builder $query): void
    {
        $query->where('is_active', true)->where('accepts_appointments', true);
    }

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'is_active' => 'boolean',
            'accepts_appointments' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
