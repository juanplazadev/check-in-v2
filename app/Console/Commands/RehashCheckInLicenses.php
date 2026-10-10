<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Encryption\BlindIndex;
use App\Models\CheckIn;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Recomputes the license blind index on every check-in. Run once after the
 * column is added, and again after DB_ENCRYPTION_KEY rotates, since the hash
 * key is derived from it. Safe to rerun.
 *
 * Writes through the query builder so the run does not touch updated_at or
 * fire the history and broadcast listeners.
 */
#[Signature('check-ins:rehash-licenses')]
#[Description('Recompute the searchable license hash on every check-in')]
final class RehashCheckInLicenses extends Command
{
    public function handle(BlindIndex $blindIndex): int
    {
        $rehashed = 0;

        CheckIn::withTrashed()
            ->select(['id', 'drivers_license_number'])
            ->chunkById(500, function (Collection $checkIns) use ($blindIndex, &$rehashed): void {
                /** @var CheckIn $checkIn */
                foreach ($checkIns as $checkIn) {
                    CheckIn::withTrashed()
                        ->whereKey($checkIn->id)
                        ->toBase()
                        ->update(['drivers_license_hash' => $blindIndex->hash($checkIn->drivers_license_number)]);

                    $rehashed++;
                }
            });

        $this->info(sprintf('Rehashed %d check-in license(s).', $rehashed));

        return self::SUCCESS;
    }
}
