<?php

declare(strict_types=1);

use App\Enums\ReferenceType;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Reference\ReferenceNumberGenerator;
use Tests\Support\SequencedRandomCode;

function references(): ReferenceNumberGenerator
{
    return resolve(ReferenceNumberGenerator::class);
}

/**
 * @param  list<string>  $suffixes
 */
function referencesDrawing(array $suffixes): ReferenceNumberGenerator
{
    return new ReferenceNumberGenerator(new SequencedRandomCode($suffixes));
}

it('prefixes the reference with the record type', function (ReferenceType $type, string $prefix): void {
    $reference = references()->generate($type);

    expect($reference)->toStartWith($prefix)
        ->and(mb_strlen($reference))->toBe(8);
})->with([
    'check-in' => [ReferenceType::CheckIn, 'C'],
    'appointment' => [ReferenceType::Appointment, 'A'],
]);

it('draws only from the unambiguous alphabet', function (): void {
    $reference = references()->generate(ReferenceType::CheckIn);

    // 0/O and 1/I/L are the pairs a driver reads back wrong over the phone.
    expect($reference)->toMatch('/^[A-HJKMNP-Z2-9]{8}$/');
});

/*
 * The prefix is what makes a cross-table collision unrepresentable. Both types
 * drawing the identical suffix must still produce different references.
 */
it('cannot collide across tables even when the random draw repeats', function (): void {
    $checkIn = referencesDrawing(['QRSTUVW'])->generate(ReferenceType::CheckIn);
    $appointment = referencesDrawing(['QRSTUVW'])->generate(ReferenceType::Appointment);

    expect($checkIn)->toBe('CQRSTUVW')
        ->and($appointment)->toBe('AQRSTUVW')
        ->and($checkIn)->not->toBe($appointment);
});

/*
 * The one case the prefix does not cover. References minted before the prefix
 * existed have no type character, so an old appointment can already start with
 * a 'C' and a new check-in could otherwise land on it.
 */
it('skips a prefix-less legacy reference held by the other table', function (): void {
    Appointment::factory()->create(['reference_number' => 'CQRSTUVW']);

    $reference = referencesDrawing(['QRSTUVW', 'XYZ2345'])->generate(ReferenceType::CheckIn);

    expect($reference)->toBe('CXYZ2345');
});

it('skips a reference already held by its own table', function (): void {
    CheckIn::factory()->create(['reference_number' => 'CQRSTUVW']);

    $reference = referencesDrawing(['QRSTUVW', 'XYZ2345'])->generate(ReferenceType::CheckIn);

    expect($reference)->toBe('CXYZ2345');
});

/*
 * The default scope hides a soft-deleted row, but the unique index still holds
 * its reference, so reissuing one would fail on insert.
 */
it('treats a soft-deleted record as still holding its reference', function (): void {
    CheckIn::factory()->create(['reference_number' => 'CQRSTUVW'])->delete();

    $reference = referencesDrawing(['QRSTUVW', 'XYZ2345'])->generate(ReferenceType::CheckIn);

    expect($reference)->toBe('CXYZ2345');
});
