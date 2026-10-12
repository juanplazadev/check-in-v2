<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\AddCheckInNoteAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\CheckInNoteRequest;
use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Throwable;

/**
 * Adds an internal note to a check-in. The lookup matches the detail page, so
 * a check-in at a location the user may not see is a 404, and archived
 * check-ins still take notes.
 */
final class CheckInNoteController extends Controller
{
    /**
     * @throws Throwable
     */
    public function __invoke(
        #[CurrentUser] User $user,
        string $uuid,
        CheckInNoteRequest $request,
        AddCheckInNoteAction $addNote,
    ): RedirectResponse {
        $checkIn = CheckIn::query()
            ->withTrashed()
            ->visibleTo($user)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $addNote->handle($checkIn, $user, $request->body());

        return back();
    }
}
