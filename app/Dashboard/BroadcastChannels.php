<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Models\User;
use App\Queries\DashboardAudience;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * Private channels the staff dashboard listens on. Authorised in
 * routes/channels.php.
 *
 * One channel per staff member rather than one shared feed, because staff are
 * scoped to locations: each record is pushed only to the members of its
 * {@see DashboardAudience}, so nobody receives a record they could not open.
 */
final class BroadcastChannels
{
    public const string CHECK_INS = 'dashboard.check-ins.{userUuid}';

    public const string APPOINTMENTS = 'dashboard.appointments.{userUuid}';

    /**
     * @return list<PrivateChannel>
     */
    public static function checkInsAt(int $locationId): array
    {
        return self::forAudience(self::CHECK_INS, $locationId);
    }

    /**
     * @return list<PrivateChannel>
     */
    public static function appointmentsAt(int $locationId): array
    {
        return self::forAudience(self::APPOINTMENTS, $locationId);
    }

    /**
     * @return list<PrivateChannel>
     */
    private static function forAudience(string $pattern, int $locationId): array
    {
        return array_values(resolve(DashboardAudience::class)
            ->execute($locationId)
            ->map(fn (User $user): PrivateChannel => new PrivateChannel(str_replace('{userUuid}', $user->uuid, $pattern)))
            ->all());
    }
}
