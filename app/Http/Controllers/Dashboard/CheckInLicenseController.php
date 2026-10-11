<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\RevealCheckInLicenseAction;
use App\Http\Controllers\Controller;
use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Returns a check-in's full driver's license number for the detail page's
 * reveal button. Plain JSON rather than a page prop, so the number is only
 * decrypted, and logged, when someone asks to see it. The lookup matches the
 * detail page, so a check-in at a location the user may not see is a 404.
 */
final class CheckInLicenseController extends Controller
{
    /**
     * @throws Throwable
     */
    public function __invoke(
        #[CurrentUser] User $user,
        string $uuid,
        RevealCheckInLicenseAction $revealLicense,
    ): JsonResponse {
        $checkIn = CheckIn::query()
            ->withTrashed()
            ->visibleTo($user)
            ->where('uuid', $uuid)
            ->firstOrFail();

        return response()
            ->json(['number' => $revealLicense->handle($checkIn, $user)])
            ->header('Cache-Control', 'no-store, private');
    }
}
