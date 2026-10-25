<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\CheckIn\CheckInScheduleResolver;
use App\Enums\CheckInPauseDuration;
use App\Models\Location;
use App\Models\User;
use App\Queries\LocationDirectory;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Staff pausing new check-ins at a location. The reason is shown to drivers.
 */
#[FailOnUnknownFields]
final class LocationCheckInPauseRequest extends FormRequest
{
    /**
     * The location being paused, scoped like the location's page, so one the
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
     * Who may pause is enforced by the route's permission middleware, and
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
        return [
            'reason' => ['required', 'string', 'max:255'],
            'duration' => ['required', Rule::enum(CheckInPauseDuration::class)],
        ];
    }

    /**
     * Rest of today needs a check-in closing time today that is still ahead.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(CheckInScheduleResolver $schedule): array
    {
        return [
            function (Validator $validator) use ($schedule): void {
                $location = $this->location;

                if ($validator->errors()->isNotEmpty() || ! $location instanceof Location || $validator->getData()['duration'] !== CheckInPauseDuration::RestOfToday->value) {
                    return;
                }

                $now = now()->setTimezone($location->timezone);
                $closeTime = $schedule->resolveSchedule($location, $now)->closeTime;

                if (! $closeTime instanceof CarbonInterface || $closeTime->lte($now)) {
                    $validator->errors()->add('duration', 'Check-ins have no closing time left today. Choose another duration.');
                }
            },
        ];
    }

    public function duration(): CheckInPauseDuration
    {
        return CheckInPauseDuration::from((string) $this->validated('duration'));
    }

    /**
     * A location the user may not see is a 404 before any validation.
     */
    protected function prepareForValidation(): void
    {
        abort_unless($this->location instanceof Location, 404);
    }
}
