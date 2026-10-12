<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\CheckInColumnsRequest;
use App\Models\User;
use App\Queries\DashboardCheckInColumns;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

/**
 * Saves which check-in table columns the user shows and in what order, on
 * their account so the choice follows them to any device. Visibility is merged
 * over earlier choices, so a column no longer offered keeps its setting should
 * it come back; a new order replaces the old one.
 */
final class CheckInColumnController extends Controller
{
    public function __invoke(#[CurrentUser] User $user, CheckInColumnsRequest $request): Response
    {
        $config = $user->config ?? [];
        $columns = $request->columns();

        if ($columns !== []) {
            $saved = data_get($config, DashboardCheckInColumns::CONFIG_KEY, []);

            data_set($config, DashboardCheckInColumns::CONFIG_KEY, [
                ...(is_array($saved) ? $saved : []),
                ...$columns,
            ]);
        }

        $order = $request->order();

        if ($order !== null) {
            data_set($config, DashboardCheckInColumns::ORDER_CONFIG_KEY, $order);
        }

        $user->update(['config' => $config]);

        return response()->noContent();
    }
}
