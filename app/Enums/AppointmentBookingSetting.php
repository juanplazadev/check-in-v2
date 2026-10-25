<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Location;
use Illuminate\Validation\Rule;

/**
 * The booking rules a location sets for its appointments. Each value is the
 * setting's key under the location's `appointment` config; a missing one
 * falls back to the app default in `app.appointment_booking`.
 */
enum AppointmentBookingSetting: string
{
    case MaxBookingDaysAhead = 'max_booking_days_ahead';
    case SlotIntervalMinutes = 'slot_interval_minutes';
    case MinLeadTimeMinutes = 'min_lead_time_minutes';
    case BufferBeforeCloseMinutes = 'buffer_before_close_minutes';

    /** Lead time and buffer are set in steps of this many minutes, as the page's sliders move. */
    public const int MINUTE_STEP = 15;

    /**
     * The setting's path in the location's config, e.g. "appointment.slot_interval_minutes".
     */
    public function configKey(): string
    {
        return 'appointment.'.$this->value;
    }

    /**
     * The value the location books with now, default and caps applied.
     */
    public function current(Location $location): int
    {
        return match ($this) {
            self::MaxBookingDaysAhead => $location->appointmentMaxBookingDaysAhead(),
            self::SlotIntervalMinutes => $location->appointmentSlotIntervalMinutes(),
            self::MinLeadTimeMinutes => $location->appointmentMinLeadTimeMinutes(),
            self::BufferBeforeCloseMinutes => $location->appointmentBufferBeforeCloseMinutes(),
        };
    }

    /**
     * The values an admin may save: the range the {@see Location} accessors
     * honour, so a saved value is never silently clamped.
     *
     * @return array<int, mixed>
     */
    public function rules(): array
    {
        return match ($this) {
            self::MaxBookingDaysAhead => ['integer', 'min:1', 'max:'.(int) config('app.appointment_booking.max_days_ahead')],
            self::SlotIntervalMinutes => ['integer', Rule::in(config('app.appointment_booking.slot_interval_minutes.allowed'))],
            self::MinLeadTimeMinutes => ['integer', 'min:0', 'max:'.(int) config('app.appointment_booking.lead_time_minutes.max'), 'multiple_of:'.self::MINUTE_STEP],
            self::BufferBeforeCloseMinutes => ['integer', 'min:0', 'max:'.(int) config('app.appointment_booking.buffer_before_close_minutes.max'), 'multiple_of:'.self::MINUTE_STEP],
        };
    }
}
