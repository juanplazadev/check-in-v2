<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecordHistoryEvent;
use App\Models\CheckIn;
use App\Models\RecordHistory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * @extends Factory<RecordHistory>
 */
final class RecordHistoryFactory extends Factory
{
    #[Override]
    protected $model = RecordHistory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recordable_type' => (new CheckIn)->getMorphClass(),
            'recordable_id' => CheckIn::factory(),
            'event' => RecordHistoryEvent::Created,
            'subject' => null,
            'channel' => null,
            'locale' => 'en',
            'user_id' => null,
            'context' => null,
        ];
    }

    /** Named forRecord rather than for, which Factory already defines. */
    public function forRecord(Model $record): static
    {
        return $this->state(fn (array $attributes): array => [
            'recordable_type' => $record->getMorphClass(),
            'recordable_id' => $record->getKey(),
        ]);
    }
}
