<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dashboard\LocationAbbreviation;
use App\Dashboard\WeeklyHours;
use App\Enums\LocationWizardStep;
use App\Enums\RecordHistoryEvent;
use App\Enums\ScheduleFeature;
use App\Enums\UserHistoryEvent;
use App\Models\Address;
use App\Models\Location;
use App\Models\User;
use App\Queries\LocationStaff;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Creates a location from the add location wizard's finished draft: its
 * address, already geocoded on the address step, the location itself with a
 * code made up from its name, the weekly hours of each feature turned on, and
 * the staff picked for it. The
 * creation is logged on the location's timeline and the admin's trail; each
 * added user is logged by AttachLocationUsersAction.
 */
final readonly class CreateLocationAction
{
    public function __construct(
        private CreateLocationHoursAction $createHours,
        private AttachLocationUsersAction $attachUsers,
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
        private LocationAbbreviation $abbreviations,
        private WeeklyHours $weeklyHours,
        private LocationStaff $staff,
    ) {}

    /**
     * @param  array<string, array<string, mixed>>  $draft  LocationWizardSession::all(), keyed by step value.
     *
     * @throws Throwable
     */
    public function handle(User $actor, array $draft): Location
    {
        $details = $draft[LocationWizardStep::Details->value];
        $address = $draft[LocationWizardStep::Address->value];
        $placement = $draft[LocationWizardStep::Placement->value];
        $features = $draft[LocationWizardStep::Features->value];
        $hours = $draft[LocationWizardStep::Hours->value];
        $userUuids = $draft[LocationWizardStep::Users->value]['users'] ?? [];

        return DB::transaction(function () use ($actor, $details, $address, $placement, $features, $hours, $userUuids): Location {
            $location = Location::query()->create([
                'uuid' => (string) Str::uuid(),
                'address_id' => Address::query()->create(Arr::only($address, [
                    'street1', 'street2', 'city', 'state', 'zip_code', 'zip_code_4', 'country', 'latitude', 'longitude',
                ]))->id,
                'name' => $details['name'],
                'abbreviation' => $this->abbreviations->suggest($details['name']),
                'email' => $details['email'],
                'phone' => $details['phone'],
                'phone_ext' => $details['phone_ext'] ?? null,
                'timezone' => $placement['timezone'],
                'max_distance_allowed' => $placement['max_distance_allowed'],
                'is_active' => true,
                'is_checkins_enabled' => (bool) $features['is_checkins_enabled'],
                'is_appointments_enabled' => (bool) $features['is_appointments_enabled'],
            ]);

            foreach (ScheduleFeature::cases() as $feature) {
                $days = $hours[$this->hoursKey($feature)] ?? null;

                if ($this->isEnabled($location, $feature) && is_array($days)) {
                    $this->createHours->handle($location, $feature, $this->weeklyHours->toSchedule($days));
                }
            }

            $this->recordHistory->handle($location, RecordHistoryEvent::Created, user: $actor);

            $this->recordUserHistory->handle($actor, UserHistoryEvent::LocationCreated, [
                'location' => $location->uuid,
            ]);

            // Re-checked here: someone picked on the users step may have changed role since.
            if ($userUuids !== []) {
                $this->attachUsers->handle($location, $this->staff->assignable($userUuids), $actor);
            }

            return $location;
        });
    }

    /**
     * The feature's key in the hours step's answers.
     */
    private function hoursKey(ScheduleFeature $feature): string
    {
        return match ($feature) {
            ScheduleFeature::CheckIns => 'checkins',
            ScheduleFeature::Appointments => 'appointments',
        };
    }

    private function isEnabled(Location $location, ScheduleFeature $feature): bool
    {
        return match ($feature) {
            ScheduleFeature::CheckIns => $location->is_checkins_enabled,
            ScheduleFeature::Appointments => $location->is_appointments_enabled,
        };
    }
}
