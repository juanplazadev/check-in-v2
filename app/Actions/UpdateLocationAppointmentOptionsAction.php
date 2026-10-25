<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\AppointmentBookingSetting;
use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Applies an admin edit to a location's appointment booking rules and
 * booking notification emails, both kept in the location's `appointment`
 * config, and logs every changed setting, with its before and after value, on
 * both trails: the location's timeline, with the editor as actor, and the
 * editor's own account trail. Settings are logged by config path, e.g.
 * `appointment.slot_interval_minutes`. A save that changes nothing writes
 * nothing, not even history.
 *
 * A setting's "before" is the value the location books with now, default
 * applied, so saving the shown value changes nothing. Every other config key
 * is kept as it is. Booked appointments are kept; the rules apply to the next
 * slot lookup.
 */
final readonly class UpdateLocationAppointmentOptionsAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @param  array<string, int>  $bookingSettings  Keyed by {@see AppointmentBookingSetting} value; only the settings sent.
     * @param  list<string>|null  $emailAddresses  Null when the addresses were not sent.
     * @return array<string, array{from: mixed, to: mixed}> The changed settings; empty when nothing changed.
     *
     * @throws Throwable
     */
    public function handle(Location $location, User $user, array $bookingSettings, ?array $emailAddresses): array
    {
        return DB::transaction(function () use ($location, $user, $bookingSettings, $emailAddresses): array {
            // Locked so two admins saving at once cannot log from the same "before".
            $location = Location::query()->lockForUpdate()->findOrFail($location->id);

            $config = $location->config ?? [];
            $changes = [];

            foreach ($bookingSettings as $key => $value) {
                $setting = AppointmentBookingSetting::from($key);
                $before = $setting->current($location);

                if ($before !== $value) {
                    data_set($config, $setting->configKey(), $value);
                    $changes[$setting->configKey()] = ['from' => $before, 'to' => $value];
                }
            }

            if ($emailAddresses !== null) {
                $before = $location->appointmentEmailAddresses();

                if (! $this->sameAddresses($before, $emailAddresses)) {
                    data_set($config, 'appointment.email_addresses', $emailAddresses);
                    $changes['appointment.email_addresses'] = ['from' => $before, 'to' => $emailAddresses];
                }
            }

            if ($changes === []) {
                return [];
            }

            $location->update(['config' => $config]);

            $this->recordHistory->handle($location, RecordHistoryEvent::AppointmentOptionsEdited, user: $user, context: $changes);

            $this->recordUserHistory->handle($user, UserHistoryEvent::LocationAppointmentOptionsEdited, [
                'location' => $location->uuid,
                ...$changes,
            ]);

            return $changes;
        });
    }

    /**
     * The same addresses in any order; the order is never shown to recipients.
     *
     * @param  list<string>  $before
     * @param  list<string>  $after
     */
    private function sameAddresses(array $before, array $after): bool
    {
        $before = array_map(mb_strtolower(...), $before);
        sort($before);
        sort($after);

        return $before === $after;
    }
}
