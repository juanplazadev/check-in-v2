<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\RestoreCheckInAction;
use App\Http\Controllers\Controller;
use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Throwable;

/**
 * Brings an archived check-in back. The lookup matches the detail page, so a
 * check-in at a location the user may not see is a 404. A check-in that is
 * not archived is a 404 too.
 */
final class CheckInRestoreController extends Controller
{
    /**
     * @throws Throwable
     */
    public function __invoke(
        #[CurrentUser] User $user,
        string $uuid,
        RestoreCheckInAction $restore,
    ): RedirectResponse {
        $checkIn = CheckIn::query()
            ->onlyTrashed()
            ->visibleTo($user)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $restore->handle($checkIn, $user);

        return back();
    }
}
