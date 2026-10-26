<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ChangeRequiredPasswordAction;
use App\Auth\HomeRedirect;
use App\Http\Requests\RequiredPasswordChangeRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;
use Throwable;

/**
 * Where EnsurePasswordIsChanged holds a user whose account an admin created
 * with a temporary password, until they choose their own.
 */
final class RequiredPasswordChangeController extends Controller
{
    public function show(#[CurrentUser] User $user): Response|RedirectResponse
    {
        if (! $user->mustChangePassword()) {
            return redirect(HomeRedirect::for($user));
        }

        return inertia('Auth/ChoosePassword', [
            'mode' => 'required',
            'email' => $user->email,
        ]);
    }

    /**
     * @throws Throwable
     */
    public function update(
        #[CurrentUser] User $user,
        RequiredPasswordChangeRequest $request,
        ChangeRequiredPasswordAction $change,
    ): RedirectResponse {
        $change->handle($user, $request->string('password')->toString());

        return redirect(HomeRedirect::for($user));
    }
}
