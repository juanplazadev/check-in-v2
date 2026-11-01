<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Events\CheckInUpdated;
use App\Models\CheckIn;
use App\Models\User;
use BackedEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Applies a staff edit to a check-in's driver, load and equipment details and
 * logs every changed field, with its before and after value, on both trails:
 * the check-in's timeline, with the editor as actor, and the editor's own
 * account trail.
 *
 * The license number is written to the trails like any other change, and both
 * history actions redact it, so the trails keep the fact it changed but never
 * the number. An edit that changes nothing writes nothing, not even history.
 *
 * Only status writes broadcast from the model hook, so the edit pushes
 * CheckInUpdated itself, after the commit, to keep open dashboards current.
 */
final readonly class UpdateCheckInAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
        private SyncPurchaseOrdersAction $syncPurchaseOrders,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     *
     * @throws Throwable
     */
    public function handle(CheckIn $checkIn, User $user, array $validated): CheckIn
    {
        return DB::transaction(function () use ($checkIn, $user, $validated): CheckIn {
            // Locked so two staff saving at once cannot log from the same "before".
            $checkIn = CheckIn::query()->with('purchaseOrders')->lockForUpdate()->findOrFail($checkIn->id);

            $attributes = Arr::except($validated, ['po_numbers', 'drivers_license_number']);
            $licenseNumber = $validated['drivers_license_number'] ?? null;

            // The encrypted cast re-encrypts on every write, so an unchanged
            // number would always look dirty; only a different one is applied.
            if (is_string($licenseNumber) && $licenseNumber !== $checkIn->drivers_license_number) {
                $attributes['drivers_license_number'] = $licenseNumber;
            }

            $checkIn->fill($attributes);

            $changes = [];

            foreach (array_keys($checkIn->getDirty()) as $attribute) {
                $changes[$attribute] = [
                    'from' => $this->loggable($checkIn->getOriginal($attribute)),
                    'to' => $this->loggable($checkIn->getAttribute($attribute)),
                ];
            }

            $poChange = $this->syncPurchaseOrders->handle($checkIn, $validated['po_numbers'] ?? null);

            if ($poChange !== null) {
                $changes['po_numbers'] = $poChange;
            }

            if ($changes === []) {
                return $checkIn;
            }

            $checkIn->save();

            $this->recordHistory->handle($checkIn, RecordHistoryEvent::DetailsEdited, user: $user, context: $changes);

            $this->recordUserHistory->handle($user, UserHistoryEvent::CheckInEdited, [
                'check_in' => $checkIn->uuid,
                ...$changes,
            ]);

            event(new CheckInUpdated($checkIn->load('purchaseOrders')));

            return $checkIn;
        });
    }

    private function loggable(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
