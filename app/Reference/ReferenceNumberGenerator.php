<?php

declare(strict_types=1);

namespace App\Reference;

use App\Enums\ReferenceType;
use App\Models\Appointment;
use App\Models\CheckIn;
use Random\RandomException;

/**
 * Mints the 8-character reference a driver sees on a text, a PDF and an email.
 *
 * Shared by {@see \App\Actions\CreateCheckInAction} and
 * {@see \App\Actions\CreateAppointmentAction} so the alphabet and the length
 * cannot drift apart, which they previously could: the routine was copied
 * verbatim into both, and into both factories.
 */
final readonly class ReferenceNumberGenerator
{
    /** Total width stays 8, so the type prefix comes out of the random portion. */
    private const int RANDOM_LENGTH = 7;

    public function __construct(
        private RandomCode $random,
    ) {}

    /**
     * @throws RandomException
     */
    public function generate(ReferenceType $type): string
    {
        do {
            $reference = $type->value.$this->random->draw(self::RANDOM_LENGTH);
        } while ($this->isTaken($reference));

        return $reference;
    }

    /**
     * Both tables are checked even though the prefix already separates anything
     * minted here. References created before the prefix existed have no type
     * character, so an old appointment may start with a 'C' by chance and a new
     * check-in could land on it. That is the one case the partition does not
     * cover, and it costs a single indexed lookup to close.
     *
     * Soft-deleted rows count as taken: the unique index still holds their
     * reference, so reissuing one would fail on insert.
     */
    private function isTaken(string $reference): bool
    {
        if (CheckIn::query()->withTrashed()->where('reference_number', $reference)->exists()) {
            return true;
        }

        return (bool) Appointment::query()->withTrashed()->where('reference_number', $reference)->exists();
    }
}
