<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Auth\HomeRedirect;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

final class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        return redirect()->intended(HomeRedirect::for($request->user()));
    }
}
