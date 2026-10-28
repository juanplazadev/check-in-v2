<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\AppointmentStatus;
use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Models\Building;
use App\Models\Dock;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Renames a dock, moves it to another building, or turns it or its bookings
 * off, and logs what changed. A save that changes nothing writes no history.
 *
 * A dock with upcoming appointments placed on it cannot be turned off or
 * closed to appointments; staff move those appointments first.
 */
final readonly class UpdateDockAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @param  array{name: string, building_id: int|null, is_active: bool, accepts_appointments: bool}  $attributes
     *
     * @throws ValidationException When the dock is being turned off while upcoming appointments use it.
     * @throws Throwable
     */
    public function handle(Dock $dock, User $user, array $attributes): void
    {
        DB::transaction(function () use ($dock, $user, $attributes): void {
            // Locked so an appointment cannot be placed on the dock while it is being turned off.
            Location::query()->lockForUpdate()->findOrFail($dock->location_id);

            $closing = ($dock->is_active && ! $attributes['is_active'])
                || ($dock->accepts_appointments && ! $attributes['accepts_appointments']);

            if ($closing) {
                $this->ensureNoUpcomingAppointments($dock);
            }

            $before = $this->snapshot($dock);
            $dock->update($attributes);
            $after = $this->snapshot($dock->refresh()->load('building'));

            $changes = [];

            foreach ($after as $field => $value) {
                if ($before[$field] !== $value) {
                    $changes[$field] = ['from' => $before[$field], 'to' => $value];
                }
            }

            if ($changes === []) {
                return;
            }

            $this->recordHistory->handle($dock->location, RecordHistoryEvent::DockEdited, user: $user, context: [
                'dock' => $dock->label(),
                ...$changes,
            ]);

            $this->recordUserHistory->handle($user, UserHistoryEvent::LocationDocksEdited, [
                'location' => $dock->location->uuid,
                'dock' => $dock->label(),
                ...$changes,
            ]);
        });
    }

    /**
     * @throws ValidationException
     */
    private function ensureNoUpcomingAppointments(Dock $dock): void
    {
        $references = $dock->appointments()
            ->withoutTrashed()
            ->where('status', AppointmentStatus::Scheduled)
            ->where('scheduled_for', '>=', now())
            ->orderBy('scheduled_for')
            ->limit(5)
            ->pluck('reference_number');

        if ($references->isNotEmpty()) {
            throw ValidationException::withMessages([
                'is_active' => sprintf(
                    'Move the upcoming appointments on this dock first: %s.',
                    $references->implode(', '),
                ),
            ]);
        }
    }

    /**
     * @return array{name: string, building: string|null, is_active: bool, accepts_appointments: bool}
     */
    private function snapshot(Dock $dock): array
    {
        return [
            'name' => $dock->name,
            'building' => $dock->building instanceof Building ? $dock->building->name : null,
            'is_active' => $dock->is_active,
            'accepts_appointments' => $dock->accepts_appointments,
        ];
    }
}
