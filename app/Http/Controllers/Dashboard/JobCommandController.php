<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\SendHorizonCommandAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\JobCommandRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Restarts, pauses or resumes the Horizon workers from the jobs page.
 */
final class JobCommandController extends Controller
{
    public function __invoke(
        #[CurrentUser] User $user,
        JobCommandRequest $request,
        SendHorizonCommandAction $send,
    ): RedirectResponse {
        $command = $request->command();

        $sent = $send->handle($command, $user);

        Inertia::flash('toast', $sent > 0
            ? ['type' => 'success', 'message' => $command->successMessage()]
            : ['type' => 'error', 'message' => "Horizon isn't running, so there are no workers to control."]);

        return back();
    }
}
