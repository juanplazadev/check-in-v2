<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read int $id
 * @property-read string $uuid
 * @property-read int $address_id
 * @property-read int $max_distance_allowed
 * @property-read string $name
 * @property-read string $abbreviation
 * @property-read string $timezone
 * @property-read string $phone
 * @property-read string|null $phone_ext
 * @property-read string $email
 * @property-read bool $is_active
 * @property-read bool $is_checkins_enabled
 * @property-read bool $is_appointments_enabled
 * @property-read array<string, string>|null $config
 * @property-read CarbonImmutable $created_at
 * @property-read CarbonImmutable $updated_at
 * @property-read CarbonImmutable|null $deleted_at
 * @property-read Address $address
 * @property-read Collection<int, User> $users
 * @property-read Collection<int, CheckInSchedule> $checkinSchedule
 * @property-read Collection<int, CheckInScheduleOverride> $checkinScheduleOverrides
 * @property-read Collection<int, AppointmentSchedule> $appointmentSchedule
 * @property-read Collection<int, AppointmentScheduleOverride> $appointmentScheduleOverrides
 */
#[Fillable(['uuid', 'address_id', 'max_distance_allowed', 'name', 'abbreviation', 'timezone', 'phone', 'phone_ext', 'email', 'is_active', 'is_checkins_enabled', 'is_appointments_enabled', 'config'])]
#[Hidden(['id'])]
#[RouteKey('uuid')]
final class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @return BelongsTo<Address, $this>
     */
    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    /**
     * Staff attached to this location; see {@see User::locations()}.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * @return HasMany<CheckInSchedule, $this>
     */
    public function checkinSchedule(): HasMany
    {
        return $this->hasMany(CheckInSchedule::class);
    }

    /**
     * @return HasMany<AppointmentSchedule, $this>
     */
    public function appointmentSchedule(): HasMany
    {
        return $this->HasMany(AppointmentSchedule::class);
    }

    /**
     * @return HasMany<CheckInScheduleOverride, $this>
     */
    public function checkinScheduleOverrides(): HasMany
    {
        return $this->hasMany(CheckInScheduleOverride::class);
    }

    /**
     * @return HasMany<AppointmentScheduleOverride, $this>
     */
    public function appointmentScheduleOverrides(): HasMany
    {
        return $this->hasMany(AppointmentScheduleOverride::class);
    }

    /**
     * @return MorphMany<PurchaseOrder, $this>
     */
    public function purchaseOrders(): MorphMany
    {
        return $this->morphMany(PurchaseOrder::class, 'purchasable');
    }

    public function appointmentMaxBookingDaysAhead(): int
    {
        $cap = (int) config('app.appointment_booking.max_days_ahead');

        return max(1, min((int) data_get($this->config, 'appointment.max_booking_days_ahead', $cap), $cap));
    }

    public function appointmentSlotIntervalMinutes(): int
    {
        $default = (int) config('app.appointment_booking.slot_interval_minutes.default');
        $allowed = config('app.appointment_booking.slot_interval_minutes.allowed');
        $value = (int) data_get($this->config, 'appointment.slot_interval_minutes', $default);

        return in_array($value, $allowed, true) ? $value : $default;
    }

    public function appointmentMinLeadTimeMinutes(): int
    {
        $default = (int) config('app.appointment_booking.lead_time_minutes.default');
        $max = (int) config('app.appointment_booking.lead_time_minutes.max');

        return max(0, min((int) data_get($this->config, 'appointment.min_lead_time_minutes', $default), $max));
    }

    public function appointmentBufferBeforeCloseMinutes(): int
    {
        $default = (int) config('app.appointment_booking.buffer_before_close_minutes.default');
        $max = (int) config('app.appointment_booking.buffer_before_close_minutes.max');

        return max(0, min((int) data_get($this->config, 'appointment.buffer_before_close_minutes', $default), $max));
    }

    /**
     * Addresses notified when an appointment is booked at this location.
     *
     * @return list<string>
     */
    public function appointmentEmailAddresses(): array
    {
        return $this->emailAddresses('appointment.email_addresses');
    }

    /**
     * Addresses notified when a driver checks in at this location.
     *
     * @return list<string>
     */
    public function checkInEmailAddresses(): array
    {
        return $this->emailAddresses('checkin.email_addresses');
    }

    /**
     * Config-driven flags controlling which optional fields the check-in form
     * collects at this location. Missing flags default to hidden.
     *
     * @return array{showTruckColor: bool, showEmptyWeightLbs: bool, showTruckPlateState: bool, showTruckPlateCountry: bool, showTrailerPlateState: bool, showTrailerPlateCountry: bool, showDriversLicenseState: bool, showDriversLicenseCountry: bool, showDriversLicenseExpirationDate: bool}
     */
    public function checkInFormFields(): array
    {
        return [
            'showTruckColor' => (bool) data_get($this->config, 'checkin.show_truck_color', false),
            'showEmptyWeightLbs' => (bool) data_get($this->config, 'checkin.show_empty_weight_lbs', false),
            'showTruckPlateState' => (bool) data_get($this->config, 'checkin.show_truck_plate_state', false),
            'showTruckPlateCountry' => (bool) data_get($this->config, 'checkin.show_truck_plate_country', false),
            'showTrailerPlateState' => (bool) data_get($this->config, 'checkin.show_trailer_plate_state', false),
            'showTrailerPlateCountry' => (bool) data_get($this->config, 'checkin.show_trailer_plate_country', false),
            'showDriversLicenseState' => (bool) data_get($this->config, 'checkin.show_drivers_license_state', false),
            'showDriversLicenseCountry' => (bool) data_get($this->config, 'checkin.show_drivers_license_country', false),
            'showDriversLicenseExpirationDate' => (bool) data_get($this->config, 'checkin.show_drivers_license_expiration_date', false),
        ];
    }

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'is_active' => 'boolean',
            'is_checkins_enabled' => 'boolean',
            'is_appointments_enabled' => 'boolean',
            'config' => 'array',
        ];
    }

    /**
     * Read and validate a list of email addresses from the location config.
     *
     * @return list<string>
     */
    private function emailAddresses(string $configKey): array
    {
        $emails = data_get($this->config, $configKey, []);

        if (! is_array($emails)) {
            return [];
        }

        return array_values(array_filter(
            $emails,
            fn (mixed $email): bool => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
        ));
    }
}
