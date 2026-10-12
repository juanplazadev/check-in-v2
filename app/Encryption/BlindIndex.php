<?php

declare(strict_types=1);

namespace App\Encryption;

use SensitiveParameter;

/**
 * Keyed hashes for exact-match lookups on encrypted columns. The ciphertext
 * uses a random IV, so SQL cannot compare it; the hash of the normalized
 * value can be compared instead, and reveals nothing without the key.
 *
 * Exact match only, by design: anything partial would have to store a weaker
 * form of the secret.
 *
 * The key is derived from DB_ENCRYPTION_KEY, so rotating that key changes
 * every hash. Run `php artisan check-ins:rehash-licenses` after a rotation.
 */
final readonly class BlindIndex
{
    public function __construct(#[SensitiveParameter] private string $key) {}

    /**
     * Null when nothing searchable is left after normalizing, so a stray
     * symbol never hashes to a value that matches other blank entries.
     */
    public function hash(?string $value): ?string
    {
        $normalized = mb_strtoupper((string) preg_replace('/[^\pL\pN]/u', '', (string) $value));

        return $normalized === '' ? null : hash_hmac('sha256', $normalized, $this->key);
    }
}
