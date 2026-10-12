<?php

declare(strict_types=1);

namespace App\ShortLink;

use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\ShortLink;
use Illuminate\Support\Facades\Date;

/**
 * Turns a short code back into the record it points at.
 */
final class ShortLinkResolver
{
    public function resolve(string $code): ?ShortLink
    {
        $link = ShortLink::query()
            ->with('linkable')
            ->where('code', $code)
            ->first();

        // A soft-deleted record keeps its link row, so the relation comes back
        // null and the code should read as unknown rather than 500 downstream.
        if (! $link instanceof ShortLink || ! $link->linkable instanceof CheckIn && ! $link->linkable instanceof Appointment) {
            return null;
        }

        return $link;
    }

    /**
     * Cheap counter for "did anyone actually tap the text". Written without
     * touching `updated_at`, since a visit is not a change to the link itself.
     */
    public function recordVisit(ShortLink $link): void
    {
        ShortLink::query()
            ->whereKey($link->getKey())
            ->update([
                'visits' => $link->visits + 1,
                'last_visited_at' => Date::now(),
            ]);
    }
}
