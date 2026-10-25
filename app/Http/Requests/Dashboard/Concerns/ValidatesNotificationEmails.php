<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard\Concerns;

use Illuminate\Support\Str;

/**
 * The optional `email_addresses` list a location's options pages send: the
 * staff addresses emailed about a check-in or a booking. Shared by the
 * check-in and appointment options requests, so both lists follow one rule.
 */
trait ValidatesNotificationEmails
{
    /** How many addresses one location may email per feature. */
    public const int MAX_EMAIL_ADDRESSES = 10;

    /**
     * The notification addresses sent, or null when the section was not.
     *
     * @return list<string>|null
     */
    public function emailAddresses(): ?array
    {
        $emails = $this->validated('email_addresses');

        return is_array($emails) ? array_values(array_map(strval(...), $emails)) : null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function notificationEmailRules(): array
    {
        return [
            // An empty list is allowed: nobody is emailed.
            'email_addresses' => ['sometimes', 'present', 'array', 'max:'.self::MAX_EMAIL_ADDRESSES],
            'email_addresses.*' => ['required', 'string', 'max:255', 'email:rfc', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function notificationEmailMessages(): array
    {
        return [
            'email_addresses.max' => sprintf('Add at most %d email addresses.', self::MAX_EMAIL_ADDRESSES),
            'email_addresses.*.required' => 'Enter an email address or remove this row.',
            'email_addresses.*.email' => 'Enter a valid email address.',
            'email_addresses.*.max' => 'Email addresses may be at most 255 characters.',
            'email_addresses.*.distinct' => 'This email address is already in the list.',
        ];
    }

    /**
     * Addresses are trimmed and lowercased, so the duplicate check ignores case.
     */
    protected function normalizeNotificationEmails(): void
    {
        $emails = $this->input('email_addresses');

        if (is_array($emails)) {
            $this->merge([
                'email_addresses' => array_map(
                    fn (mixed $email): mixed => is_string($email) ? Str::lower(mb_trim($email)) : $email,
                    $emails,
                ),
            ]);
        }
    }
}
