<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Enums\ReferenceType;
use App\Models\Appointment;
use App\Models\Location;
use App\Models\User;
use App\Reference\ReferenceNumberGenerator;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;
use Override;

/**
 * @extends Factory<Appointment>
 */
final class AppointmentFactory extends Factory
{
    #[Override]
    protected $model = Appointment::class;

    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'reference_number' => resolve(ReferenceNumberGenerator::class)->generate(ReferenceType::Appointment),
            'scheduled_for' => Date::now(),
            'drivers_name' => fake()->name(),
            'drivers_cellphone' => '+1'.fake()->numerify('##########'),
            'locale' => fake()->randomElement(['en', 'es']),

            // A freshly booked appointment is Scheduled and has never been
            // cancelled or claimed. Those fields belong to the states below;
            // setting them here produced rows that contradicted themselves,
            // e.g. a "scheduled" appointment carrying a cancellation reason.
            'status' => AppointmentStatus::Scheduled,
            'cancelled_at' => null,
            'cancelled_reason' => null,
            'claimed_at' => null,
            'claimed_via' => null,
            'reminder_sent_at' => null,

            'created_at' => Date::now(),
            'updated_at' => Date::now(),

            'location_id' => Location::factory(),
            'user_id' => null,
        ];
    }

    /**
     * Attach the appointment to a driver and mirror their details onto the record,
     * the way CreateAppointmentAction does for an authenticated driver.
     *
     * Note this deliberately leaves claimed_at/claimed_via alone: those are only
     * stamped by the post-registration claim flow. Use claimed() for that case.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes): array => array_filter([
            'user_id' => $user->id,
            'drivers_name' => $user->name,
            'drivers_cellphone' => $user->cellphone,
        ], fn (mixed $value): bool => $value !== null));
    }

    public function atLocation(Location $location): static
    {
        return $this->state(fn (array $attributes): array => [
            'location_id' => $location->id,
        ]);
    }

    /**
     * Set the appointment slot. created_at follows it for past appointments so a
     * record is never booked after the slot it was booked for.
     */
    public function scheduledFor(CarbonInterface $when): static
    {
        return $this->state(fn (array $attributes): array => [
            'scheduled_for' => $when,
            'created_at' => $when->isPast() ? $when : Date::now(),
            'updated_at' => $when->isPast() ? $when : Date::now(),
        ]);
    }

    public function claimed(string $via = 'email_verification'): static
    {
        return $this->state(fn (array $attributes): array => [
            'claimed_at' => Date::now(),
            'claimed_via' => $via,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AppointmentStatus::Scheduled,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AppointmentStatus::Completed,
        ]);
    }

    public function cancelled(?string $reason = null, ?User $by = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AppointmentStatus::Cancelled,
            'cancelled_at' => Date::now(),
            'cancelled_by' => $by?->id,
            'cancelled_reason' => $reason ?? fake()->sentence(),
        ]);
    }

    public function noShow(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AppointmentStatus::NoShow,
        ]);
    }

    public function checkedIn(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AppointmentStatus::CheckedIn,
        ]);
    }

    /**
     * The day-before reminder has already gone out, so the hourly sweep must
     * leave this booking alone.
     */
    public function reminded(): static
    {
        return $this->state(fn (array $attributes): array => [
            'reminder_sent_at' => Date::now(),
        ]);
    }

    /**
     * Archive the appointment: soft-deleted, so it lists only on the archived tab.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'deleted_at' => Date::now(),
        ]);
    }
}
