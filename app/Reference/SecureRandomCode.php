<?php

declare(strict_types=1);

namespace App\Reference;

use Random\RandomException;

/**
 * The real {@see RandomCode}, drawing from the CSPRNG.
 */
final class SecureRandomCode implements RandomCode
{
    /**
     * @throws RandomException
     */
    public function draw(int $length): string
    {
        return range(1, $length)
                |> (fn ($x): array => array_map(fn (): string => self::ALPHABET[random_int(0, mb_strlen(self::ALPHABET) - 1)], $x))
                |> (fn ($x): string => implode('', $x));
    }
}
