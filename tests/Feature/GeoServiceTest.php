<?php

declare(strict_types=1);

use App\DTOs\CoordinatesDTO;
use App\Models\Address;
use App\Services\GeoService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/*
 * Geocoding addresses against the US Census Geocoder, and looking up the
 * timezone at a point with timeapi.io.
 */

function censusAddress(): Address
{
    return new Address(['street1' => '4600 Silver Hill Rd', 'street2' => 'Suite 5', 'city' => 'Washington', 'state' => 'DC', 'zip_code' => '20233']);
}

it('returns the coordinates of the first match', function (): void {
    Http::fake([
        'geocoding.geo.census.gov/geocoder/locations/address*' => Http::response([
            'result' => ['addressMatches' => [['coordinates' => ['x' => -76.927487, 'y' => 38.845989]]]],
        ]),
    ]);

    $coordinates = (new GeoService)->getLatLong(censusAddress());

    expect($coordinates?->latitude)->toBe(38.845989)
        ->and($coordinates?->longitude)->toBe(-76.927487);
});

it('sends the street, city, state and zip without street2', function (): void {
    Http::fake([
        'geocoding.geo.census.gov/geocoder/locations/address*' => Http::response(['result' => ['addressMatches' => []]]),
    ]);

    (new GeoService)->getLatLong(censusAddress());

    Http::assertSent(fn (Request $request): bool => $request->data() === [
        'street' => '4600 Silver Hill Rd',
        'city' => 'Washington',
        'state' => 'DC',
        'zip' => '20233',
        'benchmark' => 'Public_AR_Current',
        'format' => 'json',
    ]);
});

it('returns null when the address has no match', function (): void {
    Http::fake([
        'geocoding.geo.census.gov/geocoder/locations/address*' => Http::response(['result' => ['addressMatches' => []]]),
    ]);

    expect((new GeoService)->getLatLong(censusAddress()))->toBeNull();
});

it('throws when the geocoder fails', function (): void {
    Http::fake([
        'geocoding.geo.census.gov/geocoder/locations/address*' => Http::response(status: 500),
    ]);

    (new GeoService)->getLatLong(censusAddress());
})->throws(RequestException::class);

it('returns the timezone at the coordinates', function (): void {
    Http::fake(['timeapi.io/api/TimeZone/coordinate*' => Http::response(['timeZone' => 'America/Chicago'])]);

    expect((new GeoService)->getTimezone(new CoordinatesDTO(29.7604, -95.3698)))->toBe('America/Chicago');

    Http::assertSent(fn (Request $request): bool => $request->data() === ['latitude' => 29.7604, 'longitude' => -95.3698]);
});

it('returns null when the timezone lookup has no answer', function (): void {
    Http::fake(['timeapi.io/api/TimeZone/coordinate*' => Http::response(['timeZone' => ''])]);

    expect((new GeoService)->getTimezone(new CoordinatesDTO(0.0, 0.0)))->toBeNull();
});

it('throws when the timezone lookup fails', function (): void {
    Http::fake(['timeapi.io/api/TimeZone/coordinate*' => Http::response(status: 500)]);

    (new GeoService)->getTimezone(new CoordinatesDTO(29.7604, -95.3698));
})->throws(RequestException::class);
