<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Models\Location;
use Illuminate\Support\Str;

/**
 * Makes up a new location's short code from its name, free of clashes. Codes
 * are compared case-insensitively, since older codes were stored lowercase,
 * and soft-deleted locations count too, since the unique key on the column
 * covers them.
 */
final readonly class LocationAbbreviation
{
    private const int MAX_LENGTH = 4;

    /**
     * A free code from the name's initials ("North Dock Houston" is NDH), or
     * its first letters when it has a single word ("Houston" is HOUS). A taken
     * code has its tail swapped for a number until one is free (NDH2, NDH3,
     * then ND10 once the number needs two digits).
     */
    public function suggest(string $name): string
    {
        $words = preg_split('/[^A-Za-z0-9]+/', Str::ascii($name), flags: PREG_SPLIT_NO_EMPTY) ?: [];

        $base = Str::upper(implode('', array_map(fn (string $word): string => $word[0], $words)));

        if (mb_strlen($base) < 2) {
            $base = Str::upper(implode('', $words));
        }

        $base = Str::padRight(mb_substr($base, 0, self::MAX_LENGTH), 2, 'X');

        if (! $this->isTaken($base)) {
            return $base;
        }

        for ($suffix = 2; ; $suffix++) {
            $candidate = mb_substr($base, 0, self::MAX_LENGTH - mb_strlen((string) $suffix)).$suffix;

            if (! $this->isTaken($candidate)) {
                return $candidate;
            }
        }
    }

    private function isTaken(string $abbreviation): bool
    {
        return Location::withTrashed()
            ->whereRaw('upper(abbreviation) = ?', [Str::upper($abbreviation)])
            ->exists();
    }
}
