<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Dock;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<Dock>
 */
final class DockFactory extends Factory
{
    #[Override]
    protected $model = Dock::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'location_id' => Location::factory(),
            'building_id' => null,
            'name' => 'Door '.fake()->unique()->numberBetween(1, 9999),
            'is_active' => true,
            'accepts_appointments' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function walkInOnly(): static
    {
        return $this->state(['accepts_appointments' => false]);
    }
}
