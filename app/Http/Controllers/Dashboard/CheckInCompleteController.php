<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\MarkCheckInCompletedAction;
use App\Http\Controllers\Controller;
use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Throwable;

/**
 * Marks a pending check-in completed. The lookup matches the detail page, so
 * a check-in at a location the user may not see is a 404. Archived check-ins
 * are a 404 too: they are restored before anyone works them.
 */
final class CheckInCompleteController extends Controller
{
    /**
     * @throws Throwable
     */
    public function __invoke(
        #[CurrentUser] User $user,
        string $uuid,
        MarkCheckInCompletedAction $markCompleted,
    ): RedirectResponse {
        $checkIn = CheckIn::query()
            ->visibleTo($user)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $markCompleted->handle($checkIn, $user);

        return back();
    }
}
