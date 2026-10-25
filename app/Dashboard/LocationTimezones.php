<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Models\Location;
use DateTimeZone;

/**
 * The timezones a location can be set to: the US ones, plus whatever the
 * location already has, so re-saving a location set up elsewhere never fails.
 * The edit form's options and the edit request's rule both come from here.
 */
final readonly class LocationTimezones
{
    /**
     * @return list<string>
     */
    public function for(Location $location): array
    {
        $timezones = DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, 'US');

        if (! in_array($location->timezone, $timezones, true)) {
            $timezones[] = $location->timezone;
            sort($timezones);
        }

        return $timezones;
    }
}
