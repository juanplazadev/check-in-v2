<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LoadDirection;
use App\Models\LoadType;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<LoadType>
 */
final class LoadTypeFactory extends Factory
{
    #[Override]
    protected $model = LoadType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'location_id' => Location::factory(),
            'name' => 'Load '.fake()->unique()->numberBetween(1, 9999),
            'direction' => LoadDirection::Inbound,
            'duration_minutes' => 60,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function outbound(): static
    {
        return $this->state(['direction' => LoadDirection::Outbound]);
    }

    public function lasting(int $minutes): static
    {
        return $this->state(['duration_minutes' => $minutes]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
