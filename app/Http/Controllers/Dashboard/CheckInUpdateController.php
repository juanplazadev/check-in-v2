<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\UpdateCheckInAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateCheckInRequest;
use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Throwable;

/**
 * Edits a check-in's driver, load and equipment details. The request's lookup
 * matches the detail page, so a check-in at a location the user may not see is a 404.
 * Archived check-ins are a 404 too: they are restored before anyone works them.
 */
final class CheckInUpdateController extends Controller
{
    /**
     * @throws Throwable
     */
    public function __invoke(
        #[CurrentUser] User $user,
        UpdateCheckInRequest $request,
        UpdateCheckInAction $update,
    ): RedirectResponse {
        $checkIn = $request->checkIn;

        // The request already 404s an unseen check-in; this narrows the type.
        abort_unless($checkIn instanceof CheckIn, 404);

        $update->handle($checkIn, $user, $request->validated());

        return back();
    }
}
