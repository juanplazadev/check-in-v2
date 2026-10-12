<?php

declare(strict_types=1);

namespace App\Reference;

/**
 * The source of entropy behind both a reference number and a short link code.
 *
 * Shared so the alphabet is defined once. It was previously copied into four
 * places and had already begun to matter in two of them.
 *
 * An interface rather than a concrete class because a collision is otherwise a
 * one-in-27-billion event, and the guards against one cannot be covered
 * honestly without a way to force it.
 */
interface RandomCode
{
    /** Excludes ambiguous characters (0/O, 1/I/L) so a code survives being read aloud. */
    public const string ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function draw(int $length): string;
}
