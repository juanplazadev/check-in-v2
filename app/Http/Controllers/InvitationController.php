<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AcceptInvitationAction;
use App\Auth\HomeRedirect;
use App\Http\Requests\AcceptInvitationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Response;
use Throwable;

/**
 * Where an invited staff member chooses their first password. Accepting signs
 * them in: the link proved they own the address.
 */
final class InvitationController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        return inertia('Auth/ChoosePassword', [
            'mode' => 'invitation',
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function store(AcceptInvitationRequest $request, AcceptInvitationAction $accept): RedirectResponse
    {
        $user = $accept->handle(
            $request->string('email')->toString(),
            $request->string('token')->toString(),
            $request->string('password')->toString(),
        );

        Auth::login($user);
        $request->session()->regenerate();

        return redirect(HomeRedirect::for($user));
    }
}
