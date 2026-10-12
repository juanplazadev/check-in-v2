<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Auth\HomeRedirect;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

final class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        return redirect()->intended(HomeRedirect::for($request->user()));
    }
}
