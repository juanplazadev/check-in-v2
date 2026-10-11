<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CheckIn;
use App\Models\CheckInNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<CheckInNote>
 */
final class CheckInNoteFactory extends Factory
{
    #[Override]
    protected $model = CheckInNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'check_in_id' => CheckIn::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
        ];
    }
}
