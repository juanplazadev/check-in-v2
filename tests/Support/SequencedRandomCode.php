<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Reference\RandomCode;
use App\Reference\SecureRandomCode;

/**
 * Hands out a scripted sequence of codes instead of random ones, so a test can
 * force the collision the generators are supposed to survive. Falls back to
 * real randomness once the script runs out.
 */
final class SequencedRandomCode implements RandomCode
{
    /**
     * @param  list<string>  $remaining
     */
    public function __construct(private array $remaining) {}

    public function draw(int $length): string
    {
        if ($this->remaining === []) {
            return new SecureRandomCode()->draw($length);
        }

        return array_shift($this->remaining);
    }
}
