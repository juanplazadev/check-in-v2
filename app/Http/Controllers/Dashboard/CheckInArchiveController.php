<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\ArchiveCheckInAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\CheckInArchiveRequest;
use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Throwable;

/**
 * Archives a completed or cancelled check-in, with an optional reason. The
 * lookup matches the detail page, so a check-in at a location the user may
 * not see is a 404. An already archived check-in is a 404 too.
 */
final class CheckInArchiveController extends Controller
{
    /**
     * @throws Throwable
     */
    public function __invoke(
        #[CurrentUser] User $user,
        string $uuid,
        CheckInArchiveRequest $request,
        ArchiveCheckInAction $archive,
    ): RedirectResponse {
        $checkIn = CheckIn::query()
            ->visibleTo($user)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $archive->handle($checkIn, $user, $request->reason());

        return back();
    }
}
