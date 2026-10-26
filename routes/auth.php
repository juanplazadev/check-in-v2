<?php

declare(strict_types=1);

use App\Auth\HomeRedirect;
use App\Auth\RegistrationGate;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\RequiredPasswordChangeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
|
| View routes for the auth screens. Fortify (config: views => false) owns the
| POST endpoints (login, logout, register, password reset, etc.); the GET
| pages that render our own UI live here. Loaded with the "web" and "setLocale"
| middleware via bootstrap/app.php.
|
*/

Route::middleware(['guest', 'setLocale'])->group(function (): void {
    Route::get('/login', fn () => inertia('Auth/Login', [
        'status' => session('status'),
    ]))->name('login');

    Route::get('/register', fn (RegistrationGate $gate) => inertia('Auth/Register', [
        'defaultName' => $gate->name(),
    ]))
        ->middleware('registrationAllowed')
        ->name('register');

    // Request a reset link. Fortify owns POST /forgot-password (password.email).
    Route::get('/forgot-password', fn () => inertia('Auth/ForgotPassword', [
        'status' => session('status'),
    ]))->name('password.request');

    // Landing page for the emailed reset link. Fortify builds that link from the
    // password.reset named route, so it must exist even with views => false.
    // Fortify owns POST /reset-password (password.update).
    Route::get('/reset-password/{token}', fn (string $token) => inertia('Auth/ResetPassword', [
        'token' => $token,
        'email' => request('email'),
    ]))->name('password.reset');

    // Landing page for the invitation an admin sends a new staff member, who
    // chooses their first password here. The token is on the invites broker.
    Route::get('/invitation/{token}', [InvitationController::class, 'show'])->name('invitation.show');
    Route::post('/invitation', [InvitationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('invitation.accept');
});

/*
| Signed in but not yet verified. This is where the "verified" middleware sends
| drivers, and it has to exist for that middleware to work at all: Fortify runs
| with views => false, so it registers only the POST resend and the signed GET
| link, never this prompt. Deliberately not behind "verified" itself.
*/
Route::middleware(['auth', 'setLocale'])->group(function (): void {
    Route::get('/email/verify', fn (Request $request) => $request->user()->hasVerifiedEmail()
        ? redirect()->intended(HomeRedirect::for($request->user()))
        : inertia('Auth/VerifyEmail', ['status' => session('status')]))
        ->name('verification.notice');

    /*
    | Where the "password.confirm" middleware sends drivers, and missing for the
    | same reason verification.notice was: Fortify registers this GET only when
    | views are enabled (vendor routes.php, `if ($enableViews)`), so with
    | views => false it owns the POST at the same path and nothing else.
    |
    | Deliberately not behind "verified". The profile page is the escape hatch
    | for a driver who mistyped their email at registration, and gating that
    | hatch behind a screen they cannot reach would close it.
    */
    Route::get('/user/confirm-password', fn () => inertia('Auth/ConfirmPassword'))
        ->name('password.confirm');

    /*
    | Where EnsurePasswordIsChanged holds a user an admin created with a
    | temporary password. Not behind "verified": admin-created accounts are
    | verified already, and this page must stay reachable regardless.
    */
    Route::get('/password/change', [RequiredPasswordChangeController::class, 'show'])
        ->name('password.change');
    Route::put('/password/change', [RequiredPasswordChangeController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.change.update');
});
