<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\CoordinatesDTO;
use App\Enums\RecordHistoryEvent;
use App\Enums\UserHistoryEvent;
use App\Http\Requests\Dashboard\LocationDetailsUpdateRequest;
use App\Models\Location;
use App\Models\User;
use App\Services\GeoService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Applies an admin edit to a location's details and address, and logs every
 * changed field, with its before and after value, on both trails: the
 * location's timeline, with the editor as actor, and the editor's own account
 * trail. Address fields are logged prefixed `address.`. An edit that changes
 * nothing writes nothing, not even history.
 *
 * A changed street, city, state or zip is geocoded first, so the coordinates
 * the check-in distance checks use always match the address. An address the
 * geocoder cannot find, or cannot reach, is refused and nothing is saved.
 */
final readonly class UpdateLocationDetailsAction
{
    public function __construct(
        private RecordHistoryAction $recordHistory,
        private RecordUserHistoryAction $recordUserHistory,
        private GeoService $geo,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, array{from: mixed, to: mixed}> The changed fields; empty when nothing changed.
     *
     * @throws Throwable
     */
    public function handle(Location $location, User $user, array $validated): array
    {
        // Geocoded outside the transaction so the row lock is not held over HTTP.
        $coordinates = $this->geocodeChangedAddress($location, $validated);

        return DB::transaction(function () use ($location, $user, $validated, $coordinates): array {
            // Locked so two admins saving at once cannot log from the same "before".
            $location = Location::query()->with('address')->lockForUpdate()->findOrFail($location->id);
            $address = $location->address;

            $location->fill(Arr::except($validated, LocationDetailsUpdateRequest::ADDRESS_FIELDS));
            $address->fill(Arr::only($validated, LocationDetailsUpdateRequest::ADDRESS_FIELDS));

            if ($coordinates instanceof CoordinatesDTO) {
                $address->fill(['latitude' => $coordinates->latitude, 'longitude' => $coordinates->longitude]);
            }

            $changes = [...$this->changes($location), ...$this->changes($address, 'address.')];

            if ($changes === []) {
                return [];
            }

            $location->save();
            $address->save();

            $this->recordHistory->handle($location, RecordHistoryEvent::DetailsEdited, user: $user, context: $changes);

            $this->recordUserHistory->handle($user, UserHistoryEvent::LocationEdited, [
                'location' => $location->uuid,
                ...$changes,
            ]);

            return $changes;
        });
    }

    /**
     * The new address's coordinates, or null when no geocoded field changed.
     *
     * @param  array<string, mixed>  $validated
     *
     * @throws ValidationException
     */
    private function geocodeChangedAddress(Location $location, array $validated): ?CoordinatesDTO
    {
        // A clone, not replicate(): it keeps the originals isDirty() compares against.
        $candidate = (clone $location->address)->fill(Arr::only($validated, LocationDetailsUpdateRequest::ADDRESS_FIELDS));

        if (! $candidate->isDirty(['street1', 'city', 'state', 'zip_code'])) {
            return null;
        }

        try {
            $coordinates = $this->geo->getLatLong($candidate);
        } catch (ConnectionException|RequestException) {
            throw ValidationException::withMessages([
                'street1' => 'The address could not be verified right now. Please try again.',
            ]);
        }

        if (! $coordinates instanceof CoordinatesDTO) {
            throw ValidationException::withMessages([
                'street1' => 'This address could not be found. Please check the street, city, state and zip code.',
            ]);
        }

        return $coordinates;
    }

    /**
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function changes(Model $model, string $prefix = ''): array
    {
        $changes = [];

        foreach (array_keys($model->getDirty()) as $attribute) {
            $changes[$prefix.$attribute] = [
                'from' => $model->getOriginal($attribute),
                'to' => $model->getAttribute($attribute),
            ];
        }

        return $changes;
    }
}
