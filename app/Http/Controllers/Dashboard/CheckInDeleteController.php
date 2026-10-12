<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\DeleteCheckInAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\CheckInDeleteRequest;
use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Throwable;

/**
 * Permanently deletes a check-in in any status, archived included, with a
 * required reason. The lookup matches the detail page, so a check-in at a
 * location the user may not see is a 404. The detail page is gone afterwards,
 * so this lands on the check-ins list.
 */
final class CheckInDeleteController extends Controller
{
    /**
     * @throws Throwable
     */
    public function __invoke(
        #[CurrentUser] User $user,
        string $uuid,
        CheckInDeleteRequest $request,
        DeleteCheckInAction $delete,
    ): RedirectResponse {
        $checkIn = CheckIn::query()
            ->withTrashed()
            ->visibleTo($user)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $delete->handle($checkIn, $user, $request->reason());

        return to_route('dashboard.checkIns');
    }
}
