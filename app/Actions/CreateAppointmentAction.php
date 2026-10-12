<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\AppointmentLocationDTO;
use App\Enums\AppointmentStatus;
use App\Enums\RecordHistoryEvent;
use App\Enums\ReferenceType;
use App\Locale\LocaleManager;
use App\Models\Appointment;
use App\Models\Location;
use App\Models\User;
use App\Queries\AppointmentLocation;
use App\Reference\ReferenceNumberGenerator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final readonly class CreateAppointmentAction
{
    public function __construct(
        private LocaleManager $localeManager,
        private RecordHistoryAction $history,
        private ReferenceNumberGenerator $references,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     *
     * @throws Throwable
     */
    public function handle(
        array $validated,
        AppointmentLocationDTO $locationDTO,
        ?User $user = null,
    ): Appointment {
        $location = resolve(AppointmentLocation::class)->execute($locationDTO->id, true);

        throw_if(! $location instanceof Location, RuntimeException::class, 'Invalid location');

        // See CreateCheckInAction: check-then-insert is not atomic, so a
        // concurrent booking can collide on the index and needs a fresh draw.
        $appointment = retry(
            times: 3,
            callback: fn (): Appointment => Appointment::query()->create([
                'uuid' => Str::uuid()->toString(),
                'reference_number' => $this->references->generate(ReferenceType::Appointment),
                'location_id' => $location->id,
                'user_id' => $user?->id,
                'scheduled_for' => Date::parse($validated['datetime'], $location->timezone)->utc(),
                'drivers_name' => $validated['drivers_name'],
                'drivers_cellphone' => $validated['drivers_cellphone'],
                'locale' => $this->localeManager->getLocale(request()),
                'status' => AppointmentStatus::Scheduled,
            ]),
            sleepMilliseconds: 0,
            when: fn (Throwable $e): bool => $e instanceof UniqueConstraintViolationException,
        );

        // The opening row of the trail an admin reads.
        $this->history->handle(
            record: $appointment,
            event: RecordHistoryEvent::Created,
            context: ['guest' => ! $user instanceof User],
        );

        return $appointment;
    }
}
