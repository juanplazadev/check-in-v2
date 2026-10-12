<?php

declare(strict_types=1);

namespace App\ShortLink;

use App\Models\ShortLink;
use App\Reference\RandomCode;
use Random\RandomException;

/**
 * Mints the code that appears in a texted URL.
 *
 * Uses the same ambiguity-free alphabet as a reference number, so a code stays
 * readable if someone ever has to relay one out loud, and so the redirect route
 * can constrain itself to a tight uppercase pattern.
 */
final readonly class ShortCodeGenerator
{
    /** 31^7 is about 2.75e10, and the unique index catches the rest. */
    private const int LENGTH = 7;

    public function __construct(
        private RandomCode $random,
    ) {}

    /**
     * @throws RandomException
     */
    public function generate(): string
    {
        do {
            $code = $this->random->draw(self::LENGTH);
        } while (ShortLink::query()->where('code', $code)->exists());

        return $code;
    }
}
