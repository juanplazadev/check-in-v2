<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Enums\Permission;
use App\Enums\UserHistoryEvent;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\User;
use App\Models\UserHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * Shapes a user's activity trail for the user detail page: one page of
 * user_history, newest first, each row pointing at the check-ins,
 * appointments, locations and users it touched.
 *
 * A record the viewer cannot open (deleted, or at a location they do not
 * see) is named where its snapshot allows, but never linked.
 */
final readonly class UserHistoryEntryResolver
{
    public const int PER_PAGE = 25;

    /**
     * The `changes` keys that hold a record's uuid, in the order a row lists
     * them.
     *
     * @var array<string, string>
     */
    private const array RECORD_KEYS = [
        'check_in' => 'checkIn',
        'appointment' => 'appointment',
        'location' => 'location',
        'user' => 'user',
    ];

    public function __construct(private RecordHistoryEntryResolver $recordHistory) {}

    /**
     * @return LengthAwarePaginator<int, array{id: int, event: string, description: string, records: list<array{type: string, uuid: string, label: string, available: bool}>, details: string|null, createdAt: string}>
     */
    public function page(User $subject, User $viewer): LengthAwarePaginator
    {
        $page = UserHistory::query()
            ->where('user_id', $subject->id)
            ->latest()
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);

        $known = $this->knownRecords($page->getCollection(), $viewer);

        return $page->through(fn (UserHistory $entry): array => $this->entry($entry, $known));
    }

    /**
     * @param  array<string, array<string, array{label: string, available: bool}>>  $known  See {@see self::knownRecords()}.
     * @return array{id: int, event: string, description: string, records: list<array{type: string, uuid: string, label: string, available: bool}>, details: string|null, createdAt: string}
     */
    private function entry(UserHistory $entry, array $known): array
    {
        $records = [];

        foreach ($this->references($entry) as $type => $uuid) {
            $record = $known[$type][$uuid] ?? null;

            $records[] = [
                'type' => $type,
                'uuid' => $uuid,
                'label' => $record['label'] ?? $this->snapshotLabel($entry, $type),
                'available' => $record['available'] ?? false,
            ];
        }

        return [
            'id' => $entry->id,
            'event' => $entry->event->value,
            'description' => $entry->event->label(),
            'records' => $records,
            'details' => $this->details($entry),
            'createdAt' => $entry->created_at->toIso8601String(),
        ];
    }

    /**
     * The records a row points at, by type. A key holding something other
     * than a uuid, such as the location name in a deleted check-in's
     * snapshot, is not a reference.
     *
     * @return array<string, string>
     */
    private function references(UserHistory $entry): array
    {
        $references = [];

        foreach (self::RECORD_KEYS as $key => $type) {
            $value = $entry->changes[$key] ?? null;

            if (is_string($value) && Str::isUuid($value)) {
                $references[$type] = $value;
            }
        }

        return $references;
    }

    /**
     * Loads the page's referenced records in one query per type, by uuid.
     * Records the viewer may not see are left out, so they are never named.
     * A soft-deleted location is named but not available, since its page no
     * longer opens; an archived check-in or appointment still opens.
     *
     * @param  Collection<int, UserHistory>  $entries
     * @return array<string, array<string, array{label: string, available: bool}>>
     */
    private function knownRecords(Collection $entries, User $viewer): array
    {
        $uuids = [];

        foreach ($entries as $entry) {
            foreach ($this->references($entry) as $type => $uuid) {
                $uuids[$type][$uuid] = $uuid;
            }
        }

        $known = [];

        if (isset($uuids['checkIn']) && $viewer->can(Permission::ViewCheckIns)) {
            $known['checkIn'] = CheckIn::query()
                ->withTrashed()
                ->visibleTo($viewer)
                ->whereIn('uuid', array_values($uuids['checkIn']))
                ->get(['uuid', 'reference_number'])
                ->mapWithKeys(fn (CheckIn $checkIn): array => [$checkIn->uuid => ['label' => $checkIn->reference_number, 'available' => true]])
                ->all();
        }

        if (isset($uuids['appointment']) && $viewer->can(Permission::ViewAppointments)) {
            $known['appointment'] = Appointment::query()
                ->withTrashed()
                ->visibleTo($viewer)
                ->whereIn('uuid', array_values($uuids['appointment']))
                ->get(['uuid', 'reference_number'])
                ->mapWithKeys(fn (Appointment $appointment): array => [$appointment->uuid => ['label' => $appointment->reference_number, 'available' => true]])
                ->all();
        }

        if (isset($uuids['location']) && $viewer->can(Permission::ViewLocations)) {
            $locationIds = $viewer->dashboardLocationIds();

            $known['location'] = Location::query()
                ->withTrashed()
                ->when($locationIds !== null, fn (Builder $query): Builder => $query->whereKey($locationIds))
                ->whereIn('uuid', array_values($uuids['location']))
                ->get(['id', 'uuid', 'name', 'deleted_at'])
                ->mapWithKeys(fn (Location $location): array => [$location->uuid => ['label' => $location->name, 'available' => ! $location->trashed()]])
                ->all();
        }

        if (isset($uuids['user'])) {
            $known['user'] = User::query()
                ->whereIn('uuid', array_values($uuids['user']))
                ->get(['uuid', 'name'])
                ->mapWithKeys(fn (User $user): array => [$user->uuid => ['label' => $user->name, 'available' => true]])
                ->all();
        }

        return $known;
    }

    /**
     * What to call a record the viewer cannot open: a deletion's snapshot
     * names the record it deleted; anything else is named by its type.
     */
    private function snapshotLabel(UserHistory $entry, string $type): string
    {
        $changes = $entry->changes ?? [];

        $snapshot = match ([$entry->event, $type]) {
            [UserHistoryEvent::CheckInDeleted, 'checkIn'],
            [UserHistoryEvent::AppointmentDeleted, 'appointment'] => $changes['reference_number'] ?? null,
            [UserHistoryEvent::LocationDeleted, 'location'] => $changes['name'] ?? null,
            default => null,
        };

        if (is_string($snapshot) && $snapshot !== '') {
            return $snapshot;
        }

        return match ($type) {
            'checkIn' => 'Check-in',
            'appointment' => 'Appointment',
            'location' => 'Location',
            default => 'User',
        };
    }

    /**
     * A short line on what the action involved: the fields an edit changed,
     * who a PDF went to, the date a closure covers, what a role gained and
     * lost, and the reason given.
     */
    private function details(UserHistory $entry): ?string
    {
        $changes = $entry->changes ?? [];
        $parts = [];

        $fields = [];

        foreach ($changes as $field => $change) {
            if (is_array($change) && (array_key_exists('from', $change) || array_key_exists('changed', $change))) {
                $fields[] = $this->recordHistory->changedFieldLabel((string) $field);
            }
        }

        if ($fields !== []) {
            $parts[] = 'Changed '.implode(', ', $fields);
        }

        if (is_string($changes['recipient'] ?? null)) {
            $parts[] = 'Sent to '.$changes['recipient'];
        }

        if (in_array($entry->event, [UserHistoryEvent::LocationClosureAdded, UserHistoryEvent::LocationClosureRemoved], true)
            && is_string($changes['date'] ?? null)) {
            $parts[] = 'For '.Date::parse($changes['date'])->format('M j, Y');
        }

        if ($entry->event === UserHistoryEvent::RolePermissionsEdited && is_string($changes['role'] ?? null)) {
            $parts[] = 'Role: '.Str::ucfirst(str_replace('-', ' ', $changes['role']));

            foreach (['granted' => 'Granted', 'revoked' => 'Revoked'] as $key => $label) {
                if (is_array($changes[$key] ?? null) && $changes[$key] !== []) {
                    $parts[] = $label.' '.implode(', ', array_map(ucfirst(...), $changes[$key]));
                }
            }
        }

        if (is_string($changes['reason'] ?? null) && mb_trim($changes['reason']) !== '') {
            $parts[] = 'Reason: '.mb_trim($changes['reason']);
        }

        return $parts === [] ? null : implode('. ', $parts);
    }
}
