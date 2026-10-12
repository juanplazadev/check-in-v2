<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AppointmentLinkMethod;
use App\Enums\CheckInErpStatus;
use App\Enums\CheckInStatus;
use App\Enums\ErpSyncMethod;
use App\Enums\ReferenceType;
use App\Enums\TrailerChute;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\User;
use App\Reference\ReferenceNumberGenerator;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;
use Override;

/**
 * @extends Factory<CheckIn>
 */
final class CheckInFactory extends Factory
{
    /**
     * Full state names with their country, as the check-in form's geo
     * autocomplete stores them, across the three supported countries.
     *
     * @var list<array{string, string}>
     */
    private const array REGIONS = [
        ['Arizona', 'US'],
        ['California', 'US'],
        ['Texas', 'US'],
        ['New Mexico', 'US'],
        ['Sonora', 'MX'],
        ['Nuevo León', 'MX'],
        ['Ontario', 'CA'],
        ['British Columbia', 'CA'],
    ];

    #[Override]
    protected $model = CheckIn::class;

    public function definition(): array
    {
        [$destinationState, $destinationCountry] = fake()->randomElement(self::REGIONS);
        [$truckPlateState, $truckPlateCountry] = fake()->randomElement(self::REGIONS);
        [$trailerPlateState, $trailerPlateCountry] = fake()->randomElement(self::REGIONS);
        [$licenseState, $licenseCountry] = fake()->randomElement(self::REGIONS);

        return [
            'uuid' => fake()->uuid(),
            'reference_number' => resolve(ReferenceNumberGenerator::class)->generate(ReferenceType::CheckIn),
            'status' => CheckInStatus::Pending,
            'erp_status' => CheckInErpStatus::Pending,
            'customer' => fake()->company(),
            'destination_city' => fake()->city(),
            'destination_state' => $destinationState,
            'destination_country' => $destinationCountry,
            'truck_name' => fake()->word(),
            'truck_plate' => mb_strtoupper(fake()->bothify('???###')),
            'truck_color' => fake()->randomElement((array) config('app.truck_colors')),
            'truck_plate_state' => $truckPlateState,
            'truck_plate_country' => $truckPlateCountry,
            'trailer_plate' => mb_strtoupper(fake()->bothify('???###')),
            'trailer_plate_state' => $trailerPlateState,
            'trailer_plate_country' => $trailerPlateCountry,
            'trailer_chute' => fake()->randomElement(TrailerChute::cases()),
            'empty_weight_lbs' => null,
            'drivers_name' => fake()->name(),
            'drivers_cellphone' => '+1'.fake()->numerify('##########'),
            'drivers_email' => fake()->optional()->safeEmail(),
            'drivers_license_number' => mb_strtoupper(fake()->bothify('??######')),
            'drivers_license_expiration_date' => null,
            'drivers_license_state' => $licenseState,
            'drivers_license_country' => $licenseCountry,
            'loading_instructions' => null,
            'locale' => fake()->randomElement(['en', 'es']),
            'created_at' => Date::now(),
            'updated_at' => Date::now(),

            'location_id' => Location::factory(),
            'user_id' => null,
            'appointment_id' => null,
        ];
    }

    /**
     * Attach the check-in to a driver and mirror their details onto the record,
     * the way CreateCheckInAction does for an authenticated driver.
     *
     * Note this deliberately leaves claimed_at/claimed_via alone: those are only
     * stamped by the post-registration claim flow. Use claimed() for that case.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes): array => array_filter([
            'user_id' => $user->id,
            'drivers_name' => $user->name,
            'drivers_email' => $user->email,
            'drivers_cellphone' => $user->cellphone,
            'drivers_license_number' => $user->drivers_license_number,
            'drivers_license_state' => $user->drivers_license_state,
            'drivers_license_country' => $user->drivers_license_country,
            'drivers_license_expiration_date' => $user->drivers_license_expiration_date,
        ], fn (mixed $value): bool => $value !== null));
    }

    public function atLocation(Location $location): static
    {
        return $this->state(fn (array $attributes): array => [
            'location_id' => $location->id,
        ]);
    }

    /**
     * Backdate the record. Sets updated_at alongside created_at so the two never
     * disagree, which would otherwise break any ordering or cache key built on it.
     */
    public function createdAt(CarbonInterface $when): static
    {
        return $this->state(fn (array $attributes): array => [
            'created_at' => $when,
            'updated_at' => $when,
        ]);
    }

    public function claimed(string $via = 'email_verification'): static
    {
        return $this->state(fn (array $attributes): array => [
            'claimed_at' => Date::now(),
            'claimed_via' => $via,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CheckInStatus::Pending,
        ]);
    }

    public function completed(?User $by = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CheckInStatus::Completed,
            'completed_at' => Date::now(),
            'completed_by' => $by?->id,
        ]);
    }

    /**
     * Tie the check-in to the appointment it fulfils. Pass the staff member
     * for a staff link; a guest conversion has no one behind it.
     */
    public function linkedToAppointment(
        Appointment $appointment,
        AppointmentLinkMethod $via = AppointmentLinkMethod::Conversion,
        ?User $by = null,
    ): static {
        return $this->state(fn (array $attributes): array => [
            'appointment_id' => $appointment->id,
            'appointment_linked_at' => Date::now(),
            'appointment_linked_via' => $via,
            'appointment_linked_by' => $by?->id,
        ]);
    }

    /**
     * Mark the check-in as pushed to the ERP. Pass the staff member for a
     * manual sync; an automatic sync has no one behind it.
     */
    public function erpSynced(ErpSyncMethod $via = ErpSyncMethod::Automatic, ?User $by = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'erp_status' => CheckInErpStatus::Synced,
            'erp_synced_at' => Date::now(),
            'erp_synced_via' => $via,
            'erp_synced_by' => $by?->id,
        ]);
    }

    public function cancelled(?User $by = null, string $reason = 'Load canceled'): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CheckInStatus::Cancelled,
            'cancelled_at' => Date::now(),
            'cancelled_by' => $by?->id,
            'cancelled_reason' => $reason,
        ]);
    }

    /**
     * Archive the check-in: soft-deleted, so it lists only on the archived tab.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'deleted_at' => Date::now(),
        ]);
    }
}
