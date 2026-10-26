<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dashboard\LocationTimezones;
use App\DTOs\CoordinatesDTO;
use App\Models\Address;
use App\Services\GeoService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Validation\ValidationException;

/**
 * Places a new location's address for the add location wizard: its
 * coordinates, which the check-in distance checks need, and a suggested
 * timezone. An address the geocoder cannot find, or cannot reach, is refused,
 * as in UpdateLocationDetailsAction. The timezone is only a suggestion the
 * admin confirms on the next step, so a failed lookup, or a zone outside the
 * list the admin can pick from, leaves it null instead.
 */
final readonly class GeocodeLocationAddressAction
{
    public function __construct(
        private GeoService $geo,
        private LocationTimezones $timezones,
    ) {}

    /**
     * @param  array<string, mixed>  $address  The validated address fields.
     * @return array{latitude: float, longitude: float, timezone: string|null}
     *
     * @throws ValidationException
     */
    public function handle(array $address): array
    {
        try {
            $coordinates = $this->geo->getLatLong(new Address($address));
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

        return [
            'latitude' => $coordinates->latitude,
            'longitude' => $coordinates->longitude,
            'timezone' => $this->suggestTimezone($coordinates),
        ];
    }

    private function suggestTimezone(CoordinatesDTO $coordinates): ?string
    {
        try {
            $timezone = $this->geo->getTimezone($coordinates);
        } catch (ConnectionException|RequestException) {
            return null;
        }

        return in_array($timezone, $this->timezones->for(), true) ? $timezone : null;
    }
}
