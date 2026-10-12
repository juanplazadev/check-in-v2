<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserHistoryEvent;
use App\Models\User;
use App\Models\UserHistory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<UserHistory>
 */
final class UserHistoryFactory extends Factory
{
    #[Override]
    protected $model = UserHistory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // User does not use HasFactory, so build the owner by hand.
            'user_id' => fn (): int => User::query()->create([
                'name' => fake()->name(),
                'email' => fake()->unique()->safeEmail(),
                'password' => 'password',
            ])->id,
            'event' => UserHistoryEvent::ProfileUpdated,
            'changes' => null,
        ];
    }
}
