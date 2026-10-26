<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CheckInFormField;
use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Applies an admin edit to a location's check-in form fields and check-in
 * notification emails, both kept in the location's `checkin` config, and logs
 * every changed setting, with its before and after value, on both trails: the
 * location's timeline, with the editor as actor, and the editor's own account
 * trail. Settings are logged by config path, e.g. `checkin.show_truck_color`.
 * A save that changes nothing writes nothing, not even history.
 *
 * Every other config key is kept as it is. Drivers already past the location
 * gate keep the fields they were shown; the change applies from the next gate.
 */
final readonly class UpdateLocationCheckInOptionsAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @param  array<string, bool>  $formFields  Keyed by {@see CheckInFormField} value; only the flags sent.
     * @param  list<string>|null  $emailAddresses  Null when the addresses were not sent.
     * @return array<string, array{from: mixed, to: mixed}> The changed settings; empty when nothing changed.
     *
     * @throws Throwable
     */
    public function handle(Location $location, User $user, array $formFields, ?array $emailAddresses): array
    {
        return DB::transaction(function () use ($location, $user, $formFields, $emailAddresses): array {
            // Locked so two admins saving at once cannot log from the same "before".
            $location = Location::query()->lockForUpdate()->findOrFail($location->id);

            $config = $location->config ?? [];
            $changes = [];

            foreach ($formFields as $flag => $shown) {
                $field = CheckInFormField::from($flag);
                $before = (bool) data_get($config, $field->configKey(), false);

                if ($before !== $shown) {
                    data_set($config, $field->configKey(), $shown);
                    $changes[$field->configKey()] = ['from' => $before, 'to' => $shown];
                }
            }

            if ($emailAddresses !== null) {
                $before = $location->checkInEmailAddresses();

                if (! $this->sameAddresses($before, $emailAddresses)) {
                    data_set($config, 'checkin.email_addresses', $emailAddresses);
                    $changes['checkin.email_addresses'] = ['from' => $before, 'to' => $emailAddresses];
                }
            }

            if ($changes === []) {
                return [];
            }

            $location->update(['config' => $config]);

            $this->recordHistory->handle($location, RecordHistoryEvent::CheckInOptionsEdited, user: $user, context: $changes);

            $this->recordUserHistory->handle($user, UserHistoryEvent::LocationCheckInOptionsEdited, [
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
