<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The orders the errors page lists issues in, as Sentry sort values.
 */
enum ErrorIssueSort: string
{
    case LastSeen = 'date';
    case FirstSeen = 'new';
    case Events = 'freq';
    case Users = 'user';

    public const self DEFAULT = self::LastSeen;

    public function label(): string
    {
        return match ($this) {
            self::LastSeen => 'Last seen',
            self::FirstSeen => 'First seen',
            self::Events => 'Events',
            self::Users => 'Users',
        };
    }
}
