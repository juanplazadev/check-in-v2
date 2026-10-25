<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\CoordinatesDTO;
use App\Models\Address;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Geocodes addresses with the US Census Geocoder: free, keyless, and public
 * domain, so the coordinates may be stored. US addresses only. Timezones come
 * from timeapi.io, also free and keyless.
 */
final readonly class GeoService
{
    /**
     * The address's coordinates, or null when the Census has no match. Street2
     * is left out on purpose: unit numbers make the matcher miss.
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function getLatLong(Address $address): ?CoordinatesDTO
    {
        $response = Http::baseUrl(config('services.census_geocoder.url'))
            ->acceptJson()
            ->timeout(10)
            ->retry(2, 200)
            ->get('/locations/address', [
                'street' => $address->street1,
                'city' => $address->city,
                'state' => $address->state,
                'zip' => $address->zip_code,
                'benchmark' => config('services.census_geocoder.benchmark'),
                'format' => 'json',
            ])
            ->throw();

        /** @var array{x: float, y: float}|null $coordinates */
        $coordinates = $response->json('result.addressMatches.0.coordinates');

        if ($coordinates === null) {
            return null;
        }

        return new CoordinatesDTO((float) $coordinates['y'], (float) $coordinates['x']);
    }

    /**
     * The IANA timezone at the coordinates, or null when the lookup has no
     * answer. Only ever a suggestion the admin confirms, so a failed lookup is
     * the caller's to shrug off.
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function getTimezone(CoordinatesDTO $coordinates): ?string
    {
        $timezone = Http::baseUrl(config('services.timezone_lookup.url'))
            ->acceptJson()
            ->timeout(5)
            ->get('/TimeZone/coordinate', [
                'latitude' => $coordinates->latitude,
                'longitude' => $coordinates->longitude,
            ])
            ->throw()
            ->json('timeZone');

        return is_string($timezone) && $timezone !== '' ? $timezone : null;
    }
}
