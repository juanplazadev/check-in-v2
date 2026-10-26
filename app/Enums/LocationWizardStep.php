<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The steps of the add location wizard, in the order they are filled in.
 * Every step but Review stores its answers in LocationWizardSession; Review
 * only reads them back and creates the location.
 */
enum LocationWizardStep: string
{
    case Details = 'details';
    case Address = 'address';
    case Placement = 'placement';
    case Features = 'features';
    case Hours = 'hours';
    case Users = 'users';
    case Review = 'review';

    /**
     * The steps that store answers, in order.
     *
     * @return list<self>
     */
    public static function answered(): array
    {
        return array_filter(self::cases(), fn (self $step): bool => $step !== self::Review);
    }

    public function label(): string
    {
        return match ($this) {
            self::Details => 'Details',
            self::Address => 'Address',
            self::Placement => 'Map & timezone',
            self::Features => 'Features',
            self::Hours => 'Hours',
            self::Users => 'Users',
            self::Review => 'Review',
        };
    }

    public function next(): ?self
    {
        return self::cases()[$this->position() + 1] ?? null;
    }

    public function previous(): ?self
    {
        return self::cases()[$this->position() - 1] ?? null;
    }

    /**
     * Zero-based.
     */
    public function position(): int
    {
        return (int) array_search($this, self::cases(), true);
    }
}
