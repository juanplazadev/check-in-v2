<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\EmailCheckInPdfAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\CheckInPdfEmailRequest;
use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Throwable;

/**
 * Emails a check-in's PDF to the driver. The lookup matches the detail page,
 * so a check-in at a location the user may not see is a 404, and archived
 * check-ins can still be sent.
 */
final class CheckInPdfEmailController extends Controller
{
    /**
     * @throws Throwable
     */
    public function __invoke(
        #[CurrentUser] User $user,
        string $uuid,
        CheckInPdfEmailRequest $request,
        EmailCheckInPdfAction $emailPdf,
    ): RedirectResponse {
        $checkIn = CheckIn::query()
            ->withTrashed()
            ->visibleTo($user)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $emailPdf->handle($checkIn, $user);

        return back();
    }
}
