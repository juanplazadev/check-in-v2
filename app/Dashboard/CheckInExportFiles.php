<?php

declare(strict_types=1);

namespace App\Dashboard;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Scratch space for check-in exports on the private local disk. A file exists
 * only while {@see \App\Jobs\ExportCheckInsJob} writes and emails it, then is
 * deleted. Anything older than a day was left by a worker killed mid-export.
 */
final readonly class CheckInExportFiles
{
    public const string DIRECTORY = 'exports/check-ins';

    public const int KEEP_DAYS = 1;

    public function disk(): Filesystem
    {
        return Storage::disk('local');
    }

    public function path(string $exportUuid): string
    {
        return self::DIRECTORY.'/'.$exportUuid.'.xlsx';
    }

    /**
     * Deletes every leftover older than {@see self::KEEP_DAYS}.
     */
    public function prune(CarbonInterface $now): int
    {
        $disk = $this->disk();
        $cutoff = $now->copy()->subDays(self::KEEP_DAYS)->getTimestamp();
        $expired = array_values(array_filter(
            $disk->files(self::DIRECTORY),
            fn (string $path): bool => $disk->lastModified($path) < $cutoff,
        ));

        $disk->delete($expired);

        return count($expired);
    }
}
