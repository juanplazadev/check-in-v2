<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountPdfController;
use App\Http\Controllers\AccountSettingsController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\CheckInDistanceController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\Dashboard\AppointmentController as DashboardAppointmentController;
use App\Http\Controllers\Dashboard\CheckInColumnController as DashboardCheckInColumnController;
use App\Http\Controllers\Dashboard\CheckInController as DashboardCheckInController;
use App\Http\Controllers\Dashboard\LiveQueueController;
use App\Http\Controllers\Dashboard\LocationController as DashboardLocationController;
use App\Http\Controllers\Dashboard\NotificationController as DashboardNotificationController;
use App\Http\Controllers\Dashboard\SearchController as DashboardSearchController;
use App\Http\Controllers\Dashboard\UserController as DashboardUserController;
use App\Http\Controllers\GeoController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\RoleMiddleware;

Route::prefix('locale')->group(function (): void {

    Route::post('/{locale}', LocaleController::class)
        ->where('locale', implode('|', config('app.locales')))
        ->name('localeSwitch');
});

// JSON endpoint, no translations needed
Route::middleware('throttle:30,1')
    ->post('/check-in/distance', CheckInDistanceController::class)
    ->name('checkIn.distance');

// JSON geo-autocomplete endpoints, no translations needed
Route::middleware('throttle:60,1')->group(function (): void {

    Route::get('/geo/cities', [GeoController::class, 'cities'])->name('geo.cities');

    Route::get('/geo/states', [GeoController::class, 'states'])->name('geo.states');
});

Route::middleware('setLocale')->group(function (): void {

    /**
     * Public
     */
    Route::inertia('/', 'Home')->name('home');

    Route::get('/schedule', [ScheduleController::class, 'index'])->name('schedule');

    Route::get('/contact', [ContactController::class, 'index'])->name('contact');

    Route::inertia('/privacy', 'PrivacyPolicy')->name('privacy');

    Route::inertia('/terms', 'TermsOfService')->name('terms');

    /**
     * Authenticated driver account, reachable before the email is verified.
     *
     * Deliberately outside the verified group below, so a driver who mistyped
     * their address on registration can still correct it or walk away. The
     * profile update nulls email_verified_at and re-sends the link, which makes
     * this the self-service way out of an unverifiable account; without it every
     * typo becomes a support ticket. Fortify's own user/profile-information,
     * user/password and logout endpoints carry no verified middleware either,
     * for the same reason.
     *
     * Drivers and admins only (Role::accountHolders()); the other staff roles
     * have no driver account and get a 403.
     */
    Route::middleware(['auth', RoleMiddleware::using(Role::accountHolders())])->group(function (): void {
        // password.confirm, not verified: this page decrypts and displays the
        // driver's licence number - the one field encrypted at rest - and is
        // also where the email that guards the account is changed. A live
        // session on an unlocked phone should not be enough for either. The
        // confirmation lasts config('auth.password_timeout'), so it costs one
        // password entry per session rather than one per action.
        Route::middleware('password.confirm')
            ->get('/account/profile', [AccountController::class, 'editProfile'])
            ->name('account.profile');

        // Named for the account rather than the settings, because that is what it
        // destroys. Throttled harder than the preference saves: it is irreversible,
        // and the password check makes it worth guessing at. Ungated on purpose:
        // erasure should never be conditional on verifying an address.
        Route::middleware('throttle:6,1')
            ->delete('/account', [AccountSettingsController::class, 'destroy'])
            ->name('account.destroy');
    });

    /**
     * The rest of the account surface, gated on a verified email.
     *
     * Nothing here is meaningful until then: ClaimPendingRecordsAction only
     * attaches the driver's guest check-in or appointment once the Verified
     * event fires, so an unverified account genuinely has no history to show.
     */
    Route::middleware(['auth', 'verified', RoleMiddleware::using(Role::accountHolders())])->group(function (): void {
        Route::get('/account', [AccountController::class, 'index'])->name('account');
        Route::get('/account/settings', [AccountSettingsController::class, 'index'])->name('account.settings');

        Route::middleware('throttle:30,1')
            ->patch('/account/settings', [AccountSettingsController::class, 'update'])
            ->name('account.settings.update');

        Route::get('/account/history', [AccountController::class, 'history'])
            ->name('account.history');

        Route::get('/account/history/check-in/{uuid}', [AccountController::class, 'showCheckIn'])
            ->whereUuid('uuid')
            ->name('account.history.checkIn');

        Route::get('/account/history/appointment/{uuid}', [AccountController::class, 'showAppointment'])
            ->whereUuid('uuid')
            ->name('account.history.appointment');

        // Rendering is bounded work behind a cache, but still worth throttling.
        Route::middleware('throttle:10,1')->group(function (): void {
            Route::get('/account/history/check-in/{uuid}/pdf', [AccountPdfController::class, 'checkIn'])
                ->whereUuid('uuid')
                ->name('account.history.checkIn.pdf');

            Route::get('/account/history/appointment/{uuid}/pdf', [AccountPdfController::class, 'appointment'])
                ->whereUuid('uuid')
                ->name('account.history.appointment.pdf');
        });

        // Irreversible and it mails the dock, so throttled like the other
        // state-changing account actions rather than like a page view.
        Route::middleware('throttle:6,1')
            ->post('/account/history/appointment/{uuid}/cancel', [AccountController::class, 'cancelAppointment'])
            ->whereUuid('uuid')
            ->name('account.history.appointment.cancel');

        // Tighter: each one queues a render and sends mail.
        Route::middleware('throttle:5,1')->group(function (): void {
            Route::post('/account/history/check-in/{uuid}/email', [AccountPdfController::class, 'emailCheckIn'])
                ->whereUuid('uuid')
                ->name('account.history.checkIn.email');

            Route::post('/account/history/appointment/{uuid}/email', [AccountPdfController::class, 'emailAppointment'])
                ->whereUuid('uuid')
                ->name('account.history.appointment.email');
        });
    });

    /**
     * Staff dashboard, for every staff role. Check-ins, the live queue and users
     * run on real data; the other pages still render mock data until their
     * backend pass. User and location management is admin-only.
     */
    Route::middleware(['auth', 'verified', RoleMiddleware::using(Role::staff())])->group(function (): void {
        Route::inertia('/dashboard', 'Dashboard/Index')->name('dashboard');

        Route::prefix('dashboard')->name('dashboard.')->group(function (): void {

            Route::get('/queue', LiveQueueController::class)->name('queue');

            Route::middleware('throttle:60,1')
                ->get('/search', DashboardSearchController::class)
                ->name('search');

            Route::get('/check-ins', [DashboardCheckInController::class, 'index'])->name('checkIns');
            Route::patch('/check-ins/columns', DashboardCheckInColumnController::class)->name('checkIns.columns');
            Route::get('/check-ins/{uuid}', [DashboardCheckInController::class, 'show'])
                ->whereUuid('uuid')
                ->name('checkIns.show');

            Route::post('/notifications/read-all', [DashboardNotificationController::class, 'readAll'])
                ->name('notifications.readAll');
            Route::post('/notifications/{id}/read', [DashboardNotificationController::class, 'read'])
                ->whereUuid('id')
                ->name('notifications.read');

            Route::inertia('/appointments', 'Dashboard/Appointments/Index')->name('appointments');
            Route::get('/appointments/{uuid}', [DashboardAppointmentController::class, 'show'])
                ->whereUuid('uuid')
                ->name('appointments.show');

            Route::middleware(RoleMiddleware::using(Role::Admin))->group(function (): void {
                Route::get('/users', [DashboardUserController::class, 'index'])->name('users');
                Route::get('/users/{uuid}', [DashboardUserController::class, 'show'])
                    ->whereUuid('uuid')
                    ->name('users.show');

                Route::get('/locations', [DashboardLocationController::class, 'index'])->name('locations');
                Route::get('/locations/{uuid}', [DashboardLocationController::class, 'show'])
                    ->whereUuid('uuid')
                    ->name('locations.show');
            });
        });
    });

    /**
     * Check-In
     */
    Route::get('/check-in/select-location', [CheckInController::class, 'selectLocation'])
        ->name('checkIn.selectLocation');

    Route::middleware(['userCoordinates', 'throttle:30,1'])
        ->post('/check-in/{uuid}', [CheckInController::class, 'gate'])
        ->whereUuid('uuid')
        ->name('checkIn.gate');

    Route::middleware(['checkInGatePass', 'userCoordinates'])->group(function (): void {

        Route::get('/check-in/{uuid}/form', [CheckInController::class, 'form'])
            ->whereUuid('uuid')
            ->name('checkIn.form');

        Route::middleware('throttle:10,1')
            ->post('/check-in/{uuid}/form', [CheckInController::class, 'store'])
            ->whereUuid('uuid')
            ->name('checkIn.store');
    });

    Route::middleware('throttle:20,1')
        ->get('/check-in/{uuid}/confirmed', [CheckInController::class, 'confirmed'])
        ->whereUuid('uuid')
        ->name('checkIn.confirmed');

    /**
     * Appointment
     */
    Route::get('/appointment/select-location', [AppointmentController::class, 'selectLocation'])
        ->name('appointment.selectLocation');

    Route::middleware('throttle:30,1')
        ->post('/appointment/{uuid}', [AppointmentController::class, 'gate'])
        ->name('appointment.gate');

    Route::middleware(['appointmentGatePass'])->group(function (): void {

        Route::get('/appointment/{uuid}/book', [AppointmentController::class, 'form'])
            ->name('appointment.form');

        Route::middleware('throttle:10,1')
            ->post('/appointment/{uuid}/book', [AppointmentController::class, 'store'])
            ->name('appointment.store');
    });

    Route::middleware('throttle:20,1')
        ->get('/appointment/{uuid}/confirmed', [AppointmentController::class, 'confirmed'])
        ->whereUuid('uuid')
        ->name('appointment.confirmed');
});
