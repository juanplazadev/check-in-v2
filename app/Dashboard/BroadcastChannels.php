<?php

declare(strict_types=1);

namespace App\Dashboard;

/**
 * Private channel names the staff dashboard listens on. Authorised in
 * routes/channels.php.
 */
final class BroadcastChannels
{
    public const string CHECK_INS = 'dashboard.check-ins';

    public const string APPOINTMENTS = 'dashboard.appointments';
}
