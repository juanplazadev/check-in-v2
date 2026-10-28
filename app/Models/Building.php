<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\BuildingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A building on a location's campus, used only to group its docks. A
 * location with one building needs none; its docks stay ungrouped.
 *
 * @property-read int $id
 * @property-read string $uuid
 * @property-read int $location_id
 * @property-read string $name
 * @property-read int $sort_order
 * @property-read CarbonImmutable $created_at
 * @property-read CarbonImmutable $updated_at
 * @property-read Location $location
 * @property-read Collection<int, Dock> $docks
 */
#[Fillable(['uuid', 'location_id', 'name', 'sort_order'])]
#[Hidden(['id'])]
#[RouteKey('uuid')]
final class Building extends Model
{
    /** @use HasFactory<BuildingFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return HasMany<Dock, $this>
     */
    public function docks(): HasMany
    {
        return $this->hasMany(Dock::class)->orderBy('sort_order')->orderBy('name');
    }

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'sort_order' => 'integer',
        ];
    }
}
