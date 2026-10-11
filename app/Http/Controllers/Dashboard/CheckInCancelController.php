<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\CancelCheckInAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\CheckInCancelRequest;
use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Throwable;

/**
 * Cancels a pending check-in with a reason. The lookup matches the detail
 * page, so a check-in at a location the user may not see is a 404. Archived
 * check-ins are a 404 too: they are restored before anyone works them.
 */
final class CheckInCancelController extends Controller
{
    /**
     * @throws Throwable
     */
    public function __invoke(
        #[CurrentUser] User $user,
        string $uuid,
        CheckInCancelRequest $request,
        CancelCheckInAction $cancel,
    ): RedirectResponse {
        $checkIn = CheckIn::query()
            ->visibleTo($user)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $cancel->handle($checkIn, $user, $request->reason());

        return back();
    }
}
