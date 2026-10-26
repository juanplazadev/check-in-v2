<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\AppointmentNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<AppointmentNote>
 */
final class AppointmentNoteFactory extends Factory
{
    #[Override]
    protected $model = AppointmentNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
        ];
    }
}
