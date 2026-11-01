<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\MoveAppointmentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\AppointmentMoveRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Throwable;

/**
 * Moves an appointment dropped on the dock view to a new time, dock, or both.
 */
final class AppointmentMoveController extends Controller
{
    /**
     * @throws Throwable
     */
    public function __invoke(#[CurrentUser] User $user, AppointmentMoveRequest $request, MoveAppointmentAction $move): RedirectResponse
    {
        $move->handle($request->appointment(), $user, $request->start(), $request->dockUuid());

        return back();
    }
}
