<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\ReopenCheckInAction;
use App\Http\Controllers\Controller;
use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Throwable;

/**
 * Sets a completed check-in back to pending. The lookup matches the detail
 * page, so a check-in at a location the user may not see is a 404. Archived
 * check-ins are a 404 too: they are restored before anyone works them.
 */
final class CheckInReopenController extends Controller
{
    /**
     * @throws Throwable
     */
    public function __invoke(
        #[CurrentUser] User $user,
        string $uuid,
        ReopenCheckInAction $reopen,
    ): RedirectResponse {
        $checkIn = CheckIn::query()
            ->visibleTo($user)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $reopen->handle($checkIn, $user);

        return back();
    }
}
