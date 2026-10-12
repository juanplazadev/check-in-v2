<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\CheckInLocationDTO;
use App\Enums\CheckInErpStatus;
use App\Enums\CheckInStatus;
use App\Enums\RecordHistoryEvent;
use App\Enums\ReferenceType;
use App\Locale\LocaleManager;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\User;
use App\Queries\CheckInLocation;
use App\Reference\ReferenceNumberGenerator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final readonly class CreateCheckInAction
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
        CheckInLocationDTO $locationDTO,
        ?User $user = null,
    ): CheckIn {
        $location = resolve(CheckInLocation::class)->execute($locationDTO->id);

        throw_if(! $location instanceof Location, RuntimeException::class, 'Invalid location');

        // Generating a reference and inserting it are not one atomic step, so
        // two concurrent check-ins can pass the uniqueness check and collide on
        // the index. Retrying draws a fresh reference.
        $checkIn = retry(
            times: 3,
            callback: fn (): CheckIn => CheckIn::query()->create([
                ...Arr::except($validated, ['po_numbers']),
                'uuid' => Str::uuid()->toString(),
                'reference_number' => $this->references->generate(ReferenceType::CheckIn),
                'location_id' => $location->id,
                'user_id' => $user?->id,
                'drivers_email' => $user?->email,
                'status' => CheckInStatus::Pending,
                'erp_status' => CheckInErpStatus::Pending,
                'locale' => $this->localeManager->getLocale(request()),
            ]),
            sleepMilliseconds: 0,
            when: fn (Throwable $e): bool => $e instanceof UniqueConstraintViolationException,
        );

        $this->history->handle(
            record: $checkIn,
            event: RecordHistoryEvent::Created,
            context: ['guest' => ! $user instanceof User],
        );

        return $checkIn;
    }
}
