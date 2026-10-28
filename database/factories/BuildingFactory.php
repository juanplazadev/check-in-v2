<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Building;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<Building>
 */
final class BuildingFactory extends Factory
{
    #[Override]
    protected $model = Building::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'location_id' => Location::factory(),
            'name' => 'Building '.fake()->unique()->bothify('?#'),
            'sort_order' => 0,
        ];
    }
}
