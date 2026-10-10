<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    private static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => self::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Give the user the role once it has been persisted.
     */
    public function withRole(Role $role): static
    {
        return $this->afterCreating(fn (User $user): User => $user->assignRole($role));
    }

    /**
     * Attach the user to the locations once it has been persisted. Only the
     * location-scoped roles are limited by it.
     */
    public function worksAt(Location ...$locations): static
    {
        return $this->afterCreating(fn (User $user) => $user->locations()->attach(
            array_map(fn (Location $location): int => $location->id, $locations),
        ));
    }
}
