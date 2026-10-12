<?php

declare(strict_types=1);

namespace App\Dashboard;

/**
 * Reads a dashboard search term as a phone number. Phones are stored as
 * +1XXXXXXXXXX and staff type them in any format, so "(787) 555-0142" has to
 * match on its digits, but a plate like "XQZ9081" must not match every phone
 * holding "9081".
 */
final class PhoneSearchTerm
{
    /**
     * The term's digits when it reads as a phone number, else null.
     */
    public static function digits(string $search, int $minLength): ?string
    {
        if (preg_match('/^[\d\s()+.\-]+$/', $search) !== 1) {
            return null;
        }

        $digits = (string) preg_replace('/\D/', '', $search);

        return mb_strlen($digits) >= $minLength ? $digits : null;
    }
}
