<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Enums\AppointmentBookingSetting;
use App\Http\Requests\Dashboard\Concerns\ValidatesNotificationEmails;
use App\Models\Location;
use App\Models\User;
use App\Queries\LocationDirectory;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An admin edit of one section of a location's appointment booking &
 * notifications page: its booking rules, keyed by their config key, or who
 * is emailed when an appointment is booked or canceled. Every field is
 * optional, so each section sends only its own; any field outside these
 * rules fails the whole request, so a tampered payload cannot reach the rest
 * of the config.
 */
#[FailOnUnknownFields]
final class LocationAppointmentOptionsUpdateRequest extends FormRequest
{
    use ValidatesNotificationEmails;

    /**
     * The location being edited, scoped like the location's page, so one the
     * user may not see is a 404.
     */
    public ?Location $location = null {
        get {
            $user = $this->user();

            return $this->location ??= $user instanceof User
                ? resolve(LocationDirectory::class)->find($user, (string) $this->route('uuid'))
                : null;
        }
    }

    /**
     * Who may edit is enforced by the route's permission middleware, and
     * which locations the user may see by the LocationDirectory lookup.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [];

        foreach (AppointmentBookingSetting::cases() as $setting) {
            $rules[$setting->value] = ['sometimes', 'required', ...$setting->rules()];
        }

        return [...$rules, ...$this->notificationEmailRules()];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            AppointmentBookingSetting::SlotIntervalMinutes->value.'.in' => 'Choose one of the listed slot intervals.',
            '*.multiple_of' => sprintf('The :attribute must be in steps of %d minutes.', AppointmentBookingSetting::MINUTE_STEP),
            ...$this->notificationEmailMessages(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            AppointmentBookingSetting::MaxBookingDaysAhead->value => 'days ahead',
            AppointmentBookingSetting::SlotIntervalMinutes->value => 'slot interval',
            AppointmentBookingSetting::MinLeadTimeMinutes->value => 'minimum lead time',
            AppointmentBookingSetting::BufferBeforeCloseMinutes->value => 'buffer before close',
        ];
    }

    /**
     * The booking settings sent, keyed by their config key.
     *
     * @return array<string, int>
     */
    public function bookingSettings(): array
    {
        $validated = $this->validated();
        $settings = [];

        foreach (AppointmentBookingSetting::cases() as $setting) {
            if (array_key_exists($setting->value, $validated)) {
                $settings[$setting->value] = (int) $validated[$setting->value];
            }
        }

        return $settings;
    }

    /**
     * A location the user may not see is a 404 before any validation.
     */
    protected function prepareForValidation(): void
    {
        abort_unless($this->location instanceof Location, 404);

        $this->normalizeNotificationEmails();
    }
}
