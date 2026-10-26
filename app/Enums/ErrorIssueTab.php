<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The quick filters over the errors page's issue list, each a Sentry search.
 */
enum ErrorIssueTab: string
{
    case Unresolved = 'unresolved';
    case New = 'new';
    case Regressed = 'regressed';
    case Resolved = 'resolved';

    public const self DEFAULT = self::Unresolved;

    public function label(): string
    {
        return match ($this) {
            self::Unresolved => 'Unresolved',
            self::New => 'New',
            self::Regressed => 'Regressed',
            self::Resolved => 'Resolved',
        };
    }

    /**
     * The Sentry search for the tab; new means first seen within the period.
     */
    public function query(ErrorPeriod $period): string
    {
        return match ($this) {
            self::Unresolved => 'is:unresolved',
            self::New => 'is:unresolved firstSeen:-'.$period->value,
            self::Regressed => 'is:regressed',
            self::Resolved => 'is:resolved',
        };
    }
}
