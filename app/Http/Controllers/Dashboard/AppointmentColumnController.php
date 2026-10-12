<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\AppointmentColumnsRequest;
use App\Models\User;
use App\Queries\DashboardAppointmentColumns;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

/**
 * Saves which appointment table columns the user shows and in what order, on
 * their account so the choice follows them to any device. Visibility is merged
 * over earlier choices; a new order replaces the old one.
 */
final class AppointmentColumnController extends Controller
{
    public function __invoke(#[CurrentUser] User $user, AppointmentColumnsRequest $request): Response
    {
        $config = $user->config ?? [];
        $columns = $request->columns();

        if ($columns !== []) {
            $saved = data_get($config, DashboardAppointmentColumns::CONFIG_KEY, []);

            data_set($config, DashboardAppointmentColumns::CONFIG_KEY, [
                ...(is_array($saved) ? $saved : []),
                ...$columns,
            ]);
        }

        $order = $request->order();

        if ($order !== null) {
            data_set($config, DashboardAppointmentColumns::ORDER_CONFIG_KEY, $order);
        }

        $user->update(['config' => $config]);

        return response()->noContent();
    }
}
