<?php

declare(strict_types=1);

namespace App\ShortLink;

use App\Models\ShortLink;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\UniqueConstraintViolationException;
use Random\RandomException;

/**
 * Gives a record a short URL, minted the first time one is asked for.
 *
 * Create-on-demand rather than create-on-save so records that never get texted
 * do not accumulate codes, and so this can be added to another record type
 * without a backfill.
 */
trait HasShortLink
{
    /**
     * @return MorphOne<ShortLink, $this>
     */
    public function shortLink(): MorphOne
    {
        return $this->morphOne(ShortLink::class, 'linkable');
    }

    /**
     * The public short URL for this record, creating the code if needed.
     */
    public function shortUrl(): string
    {
        return resolve(ShortLinkUrlGenerator::class)->for($this->ensureShortLink());
    }

    private function ensureShortLink(): ShortLink
    {
        $existing = $this->shortLink()->first();

        if ($existing instanceof ShortLink) {
            return $existing;
        }

        try {
            return $this->shortLink()->create([
                'code' => resolve(ShortCodeGenerator::class)->generate(),
            ]);
        } catch (UniqueConstraintViolationException|RandomException) {
            // Two queued notifications for the same record can race here. The
            // unique index on (linkable_type, linkable_id) settles it, and the
            // loser reads back the winner's row rather than minting a second.
            return $this->shortLink()->firstOrFail();
        }
    }
}
