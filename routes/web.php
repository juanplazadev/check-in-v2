<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountPdfController;
use App\Http\Controllers\AccountSettingsController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\CheckInDistanceController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\Dashboard\AnalyticsController as DashboardAnalyticsController;
use App\Http\Controllers\Dashboard\AppointmentArchiveController as DashboardAppointmentArchiveController;
use App\Http\Controllers\Dashboard\AppointmentCancelController as DashboardAppointmentCancelController;
use App\Http\Controllers\Dashboard\AppointmentColumnController as DashboardAppointmentColumnController;
use App\Http\Controllers\Dashboard\AppointmentController as DashboardAppointmentController;
use App\Http\Controllers\Dashboard\AppointmentDeleteController as DashboardAppointmentDeleteController;
use App\Http\Controllers\Dashboard\AppointmentExportController as DashboardAppointmentExportController;
use App\Http\Controllers\Dashboard\AppointmentNoteController as DashboardAppointmentNoteController;
use App\Http\Controllers\Dashboard\AppointmentPdfController as DashboardAppointmentPdfController;
use App\Http\Controllers\Dashboard\AppointmentPdfEmailController as DashboardAppointmentPdfEmailController;
use App\Http\Controllers\Dashboard\AppointmentReinstateController as DashboardAppointmentReinstateController;
use App\Http\Controllers\Dashboard\AppointmentRestoreController as DashboardAppointmentRestoreController;
use App\Http\Controllers\Dashboard\CheckInAppointmentController as DashboardCheckInAppointmentController;
use App\Http\Controllers\Dashboard\CheckInArchiveController as DashboardCheckInArchiveController;
use App\Http\Controllers\Dashboard\CheckInCancelController as DashboardCheckInCancelController;
use App\Http\Controllers\Dashboard\CheckInColumnController as DashboardCheckInColumnController;
use App\Http\Controllers\Dashboard\CheckInCompleteController as DashboardCheckInCompleteController;
use App\Http\Controllers\Dashboard\CheckInController as DashboardCheckInController;
use App\Http\Controllers\Dashboard\CheckInDeleteController as DashboardCheckInDeleteController;
use App\Http\Controllers\Dashboard\CheckInExportController as DashboardCheckInExportController;
use App\Http\Controllers\Dashboard\CheckInLicenseController as DashboardCheckInLicenseController;
use App\Http\Controllers\Dashboard\CheckInNoteController as DashboardCheckInNoteController;
use App\Http\Controllers\Dashboard\CheckInPdfController as DashboardCheckInPdfController;
use App\Http\Controllers\Dashboard\CheckInPdfEmailController as DashboardCheckInPdfEmailController;
use App\Http\Controllers\Dashboard\CheckInReinstateController as DashboardCheckInReinstateController;
use App\Http\Controllers\Dashboard\CheckInReopenController as DashboardCheckInReopenController;
use App\Http\Controllers\Dashboard\CheckInRestoreController as DashboardCheckInRestoreController;
use App\Http\Controllers\Dashboard\CheckInUpdateController as DashboardCheckInUpdateController;
use App\Http\Controllers\Dashboard\LiveQueueController;
use App\Http\Controllers\Dashboard\LocationAppointmentHoursController as DashboardLocationAppointmentHoursController;
use App\Http\Controllers\Dashboard\LocationAppointmentOptionsController as DashboardLocationAppointmentOptionsController;
use App\Http\Controllers\Dashboard\LocationAppointmentPauseController as DashboardLocationAppointmentPauseController;
use App\Http\Controllers\Dashboard\LocationCheckInHoursController as DashboardLocationCheckInHoursController;
use App\Http\Controllers\Dashboard\LocationCheckInOptionsController as DashboardLocationCheckInOptionsController;
use App\Http\Controllers\Dashboard\LocationCheckInPauseController as DashboardLocationCheckInPauseController;
use App\Http\Controllers\Dashboard\LocationClosureController as DashboardLocationClosureController;
use App\Http\Controllers\Dashboard\LocationController as DashboardLocationController;
use App\Http\Controllers\Dashboard\LocationDeleteController as DashboardLocationDeleteController;
use App\Http\Controllers\Dashboard\LocationDetailsController as DashboardLocationDetailsController;
use App\Http\Controllers\Dashboard\LocationRestoreController as DashboardLocationRestoreController;
use App\Http\Controllers\Dashboard\LocationStatusController as DashboardLocationStatusController;
use App\Http\Controllers\Dashboard\LocationUserController as DashboardLocationUserController;
use App\Http\Controllers\Dashboard\NotificationController as DashboardNotificationController;
use App\Http\Controllers\Dashboard\RolePermissionController as DashboardRolePermissionController;
use App\Http\Controllers\Dashboard\SearchController as DashboardSearchController;
use App\Http\Controllers\Dashboard\UserController as DashboardUserController;
use App\Http\Controllers\GeoController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;

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
     * Behind access driver account (drivers and admins); the other staff roles
     * have no driver account and get a 403.
     */
    Route::middleware(['auth', PermissionMiddleware::using(Permission::AccessDriverAccount)])->group(function (): void {
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
    Route::middleware(['auth', 'verified', PermissionMiddleware::using(Permission::AccessDriverAccount)])->group(function (): void {
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
     * Staff dashboard. Every route is behind its own permission, granted to
     * roles in Role::permissions(); never check a role here. Check-ins, the
     * live queue, users and locations run on real data; the other pages still
     * render mock data until their backend pass.
     */
    Route::middleware(['auth', 'verified'])->group(function (): void {
        Route::inertia('/dashboard', 'Dashboard/Index')
            ->middleware(PermissionMiddleware::using(Permission::ViewOverview))
            ->name('dashboard');

        Route::prefix('dashboard')->name('dashboard.')->group(function (): void {

            Route::middleware(PermissionMiddleware::using(Permission::ViewLiveQueue))
                ->get('/queue', LiveQueueController::class)
                ->name('queue');

            Route::middleware(PermissionMiddleware::using(Permission::ViewAnalytics))
                ->get('/analytics', DashboardAnalyticsController::class)
                ->name('analytics');

            Route::middleware(['throttle:60,1', PermissionMiddleware::using(Permission::SearchDashboard)])
                ->get('/search', DashboardSearchController::class)
                ->name('search');

            Route::middleware(PermissionMiddleware::using(Permission::ViewCheckIns))->group(function (): void {
                Route::get('/check-ins', [DashboardCheckInController::class, 'index'])->name('checkIns');

                // Which of display and order may be saved is checked per key
                // by CheckInColumnsRequest.
                Route::middleware(PermissionMiddleware::using([Permission::SaveCheckInColumnDisplay, Permission::SaveCheckInColumnOrder]))
                    ->patch('/check-ins/columns', DashboardCheckInColumnController::class)
                    ->name('checkIns.columns');

                Route::get('/check-ins/{uuid}', [DashboardCheckInController::class, 'show'])
                    ->whereUuid('uuid')
                    ->name('checkIns.show');

                // Printing needs the same bytes as viewing, so either permission opens the file.
                Route::middleware(['throttle:30,1', PermissionMiddleware::using([Permission::ViewCheckInPdf, Permission::PrintCheckInPdf])])
                    ->get('/check-ins/{uuid}/pdf', DashboardCheckInPdfController::class)
                    ->whereUuid('uuid')
                    ->name('checkIns.pdf');

                // Sent only to the driver's address on file; logged on both history trails.
                Route::middleware(['throttle:10,1', PermissionMiddleware::using(Permission::EmailCheckInPdf)])
                    ->post('/check-ins/{uuid}/pdf/email', DashboardCheckInPdfEmailController::class)
                    ->whereUuid('uuid')
                    ->name('checkIns.pdf.email');

                Route::middleware(['throttle:30,1', PermissionMiddleware::using(Permission::WriteCheckInNotes)])
                    ->post('/check-ins/{uuid}/notes', DashboardCheckInNoteController::class)
                    ->whereUuid('uuid')
                    ->name('checkIns.notes.store');

                Route::middleware(['throttle:30,1', PermissionMiddleware::using(Permission::CompleteCheckIns)])
                    ->post('/check-ins/{uuid}/complete', DashboardCheckInCompleteController::class)
                    ->whereUuid('uuid')
                    ->name('checkIns.complete');

                Route::middleware(['throttle:30,1', PermissionMiddleware::using(Permission::ReopenCheckIns)])
                    ->post('/check-ins/{uuid}/reopen', DashboardCheckInReopenController::class)
                    ->whereUuid('uuid')
                    ->name('checkIns.reopen');

                Route::middleware(['throttle:30,1', PermissionMiddleware::using(Permission::CancelCheckIns)])
                    ->post('/check-ins/{uuid}/cancel', DashboardCheckInCancelController::class)
                    ->whereUuid('uuid')
                    ->name('checkIns.cancel');

                Route::middleware(['throttle:30,1', PermissionMiddleware::using(Permission::ReinstateCheckIns)])
                    ->post('/check-ins/{uuid}/reinstate', DashboardCheckInReinstateController::class)
                    ->whereUuid('uuid')
                    ->name('checkIns.reinstate');

                Route::middleware(['throttle:30,1', PermissionMiddleware::using(Permission::ArchiveCheckIns)])
                    ->post('/check-ins/{uuid}/archive', DashboardCheckInArchiveController::class)
                    ->whereUuid('uuid')
                    ->name('checkIns.archive');

                Route::middleware(['throttle:30,1', PermissionMiddleware::using(Permission::RestoreCheckIns)])
                    ->post('/check-ins/{uuid}/restore', DashboardCheckInRestoreController::class)
                    ->whereUuid('uuid')
                    ->name('checkIns.restore');

                // Permanent, in any status. The reason is required by
                // CheckInDeleteRequest.
                Route::middleware(['throttle:30,1', PermissionMiddleware::using(Permission::DeleteCheckIns)])
                    ->delete('/check-ins/{uuid}', DashboardCheckInDeleteController::class)
                    ->whereUuid('uuid')
                    ->name('checkIns.destroy');

                Route::middleware(['throttle:30,1', PermissionMiddleware::using(Permission::EditCheckIns)])
                    ->patch('/check-ins/{uuid}', DashboardCheckInUpdateController::class)
                    ->whereUuid('uuid')
                    ->name('checkIns.update');

                // Suggestions are read when the link dialog opens. Linking by
                // reference number is throttled tighter so a staff account
                // cannot be used to walk the code space.
                Route::middleware(PermissionMiddleware::using(Permission::LinkCheckInAppointments))->group(function (): void {
                    Route::middleware('throttle:30,1')
                        ->get('/check-ins/{uuid}/appointment', [DashboardCheckInAppointmentController::class, 'index'])
                        ->whereUuid('uuid')
                        ->name('checkIns.appointment.index');

                    Route::middleware('throttle:10,1')
                        ->post('/check-ins/{uuid}/appointment', [DashboardCheckInAppointmentController::class, 'store'])
                        ->whereUuid('uuid')
                        ->name('checkIns.appointment.store');

                    Route::middleware('throttle:30,1')
                        ->delete('/check-ins/{uuid}/appointment', [DashboardCheckInAppointmentController::class, 'destroy'])
                        ->whereUuid('uuid')
                        ->name('checkIns.appointment.destroy');
                });

                // POST, not GET: every reveal writes to both history trails.
                Route::middleware(['throttle:30,1', PermissionMiddleware::using(Permission::RevealDriverLicenses)])
                    ->post('/check-ins/{uuid}/license', DashboardCheckInLicenseController::class)
                    ->whereUuid('uuid')
                    ->name('checkIns.license');

                // Queues a job that emails the file to the requester. Which
                // spreadsheet may be asked for is checked per scope by
                // CheckInExportRequest.
                Route::middleware(['throttle:5,1', PermissionMiddleware::using([Permission::ExportCheckInsView, Permission::ExportCheckInsMaster])])
                    ->post('/check-ins/exports', [DashboardCheckInExportController::class, 'store'])
                    ->name('checkIns.exports.store');
            });

            Route::middleware(PermissionMiddleware::using(Permission::ViewNotifications))->group(function (): void {
                Route::post('/notifications/read-all', [DashboardNotificationController::class, 'readAll'])
                    ->name('notifications.readAll');
                Route::post('/notifications/{id}/read', [DashboardNotificationController::class, 'read'])
                    ->whereUuid('id')
                    ->name('notifications.read');
            });

            Route::middleware(PermissionMiddleware::using(Permission::ViewAppointments))->group(function (): void {
                Route::get('/appointments', [DashboardAppointmentController::class, 'index'])->name('appointments');

                // Which of display and order may be saved is checked per key
                // by AppointmentColumnsRequest.
                Route::middleware(PermissionMiddleware::using([Permission::SaveAppointmentColumnDisplay, Permission::SaveAppointmentColumnOrder]))
                    ->patch('/appointments/columns', DashboardAppointmentColumnController::class)
                    ->name('appointments.columns');

                // Queues a job that emails the file to the requester. Which
                // spreadsheet may be asked for is checked per scope by
                // AppointmentExportRequest.
                Route::middleware(['throttle:5,1', PermissionMiddleware::using([Permission::ExportAppointmentsView, Permission::ExportAppointmentsMaster])])
                    ->post('/appointments/exports', [DashboardAppointmentExportController::class, 'store'])
                    ->name('appointments.exports.store');

                Route::middleware(['throttle:30,1', PermissionMiddleware::using(Permission::WriteAppointmentNotes)])
                    ->post('/appointments/{uuid}/notes', DashboardAppointmentNoteController::class)
                    ->whereUuid('uuid')
                    ->name('appointments.notes.store');

                // Printing needs the same bytes as viewing, so either permission opens the file.
                Route::middleware(['throttle:30,1', PermissionMiddleware::using([Permission::ViewAppointmentPdf, Permission::PrintAppointmentPdf])])
                    ->get('/appointments/{uuid}/pdf', DashboardAppointmentPdfController::class)
                    ->whereUuid('uuid')
                    ->name('appointments.pdf');

                // Sent only to the driver's account address; logged on both history trails.
                Route::middleware(['throttle:10,1', PermissionMiddleware::using(Permission::EmailAppointmentPdf)])
                    ->post('/appointments/{uuid}/pdf/email', DashboardAppointmentPdfEmailController::class)
                    ->whereUuid('uuid')
                    ->name('appointments.pdf.email');

                Route::middleware(['throttle:30,1', PermissionMiddleware::using(Permission::CancelAppointments)])
                    ->post('/appointments/{uuid}/cancel', DashboardAppointmentCancelController::class)
                    ->whereUuid('uuid')
                    ->name('appointments.cancel');

                Route::middleware(['throttle:30,1', PermissionMiddleware::using(Permission::ReinstateAppointments)])
                    ->post('/appointments/{uuid}/reinstate', DashboardAppointmentReinstateController::class)
                    ->whereUuid('uuid')
                    ->name('appointments.reinstate');

                Route::middleware(['throttle:30,1', PermissionMiddleware::using(Permission::ArchiveAppointments)])
                    ->post('/appointments/{uuid}/archive', DashboardAppointmentArchiveController::class)
                    ->whereUuid('uuid')
                    ->name('appointments.archive');

                Route::middleware(['throttle:30,1', PermissionMiddleware::using(Permission::RestoreAppointments)])
                    ->post('/appointments/{uuid}/restore', DashboardAppointmentRestoreController::class)
                    ->whereUuid('uuid')
                    ->name('appointments.restore');

                // Permanent, in any status. The reason is required by
                // AppointmentDeleteRequest.
                Route::middleware(['throttle:30,1', PermissionMiddleware::using(Permission::DeleteAppointments)])
                    ->delete('/appointments/{uuid}', DashboardAppointmentDeleteController::class)
                    ->whereUuid('uuid')
                    ->name('appointments.destroy');

                Route::get('/appointments/{uuid}', [DashboardAppointmentController::class, 'show'])
                    ->whereUuid('uuid')
                    ->name('appointments.show');
            });

            Route::middleware(PermissionMiddleware::using(Permission::ViewUsers))->group(function (): void {
                Route::get('/users', [DashboardUserController::class, 'index'])->name('users');
                Route::get('/users/{uuid}', [DashboardUserController::class, 'show'])
                    ->whereUuid('uuid')
                    ->name('users.show');
            });

            Route::middleware(PermissionMiddleware::using(Permission::ViewLocations))->group(function (): void {
                Route::get('/locations', [DashboardLocationController::class, 'index'])->name('locations');
                Route::get('/locations/{uuid}', [DashboardLocationController::class, 'show'])
                    ->whereUuid('uuid')
                    ->name('locations.show');

                // One page per section of a location, sharing LocationLayout.
                Route::prefix('/locations/{uuid}')->whereUuid('uuid')->group(function (): void {
                    Route::get('/details', [DashboardLocationController::class, 'details'])->name('locations.details');
                    Route::get('/users', [DashboardLocationController::class, 'users'])->name('locations.users');
                    Route::get('/check-ins/hours', [DashboardLocationController::class, 'checkInHours'])->name('locations.checkInHours');
                    Route::get('/check-ins/options', [DashboardLocationController::class, 'checkInOptions'])->name('locations.checkInOptions');
                    Route::get('/appointments/hours', [DashboardLocationController::class, 'appointmentHours'])->name('locations.appointmentHours');
                    Route::get('/appointments/options', [DashboardLocationController::class, 'appointmentOptions'])->name('locations.appointmentOptions');
                    Route::get('/closures', [DashboardLocationController::class, 'closures'])->name('locations.closures');
                    Route::get('/history', [DashboardLocationController::class, 'history'])->name('locations.history');

                    Route::middleware(['throttle:30,1', PermissionMiddleware::using(Permission::EditLocations)])
                        ->patch('/details', [DashboardLocationDetailsController::class, 'update'])
                        ->name('locations.details.update');

                    // Taking a location, or one of its features, out of service for drivers and guests.
                    Route::middleware(['throttle:30,1', PermissionMiddleware::using(Permission::ManageLocationStatus)])
                        ->patch('/status', [DashboardLocationStatusController::class, 'update'])
                        ->name('locations.status.update');

                    // A soft delete: past check-ins and appointments keep the location.
                    Route::middleware(['throttle:30,1', PermissionMiddleware::using(Permission::DeleteLocations)])
                        ->delete('', DashboardLocationDeleteController::class)
                        ->name('locations.destroy');

                    // An emergency stop on new check-ins; appointments carry on.
                    Route::middleware(['throttle:30,1', PermissionMiddleware::using(Permission::PauseCheckIns)])->group(function (): void {
                        Route::post('/check-ins/pause', [DashboardLocationCheckInPauseController::class, 'store'])->name('locations.checkInPause.store');
                        Route::delete('/check-ins/pause', [DashboardLocationCheckInPauseController::class, 'destroy'])->name('locations.checkInPause.destroy');
                    });

                    // Stops new bookings on every day; booked appointments and check-ins carry on.
                    Route::middleware(['throttle:30,1', PermissionMiddleware::using(Permission::PauseAppointments)])->group(function (): void {
                        Route::post('/appointments/pause', [DashboardLocationAppointmentPauseController::class, 'store'])->name('locations.appointmentPause.store');
                        Route::delete('/appointments/pause', [DashboardLocationAppointmentPauseController::class, 'destroy'])->name('locations.appointmentPause.destroy');
                    });

                    // The weekly hours drivers and guests see. Booked appointments are kept either way.
                    Route::middleware(['throttle:30,1', PermissionMiddleware::using(Permission::EditLocationHours)])->group(function (): void {
                        Route::put('/check-ins/hours', [DashboardLocationCheckInHoursController::class, 'update'])->name('locations.checkInHours.update');
                        Route::put('/appointments/hours', [DashboardLocationAppointmentHoursController::class, 'update'])->name('locations.appointmentHours.update');

                        // Dates that override those hours; past ones stay as history.
                        Route::post('/closures', [DashboardLocationClosureController::class, 'store'])->name('locations.closures.store');
                        Route::delete('/closures/{date}', [DashboardLocationClosureController::class, 'destroy'])
                            ->where('date', '\d{4}-\d{2}-\d{2}')
                            ->name('locations.closures.destroy');
                    });

                    // Which optional fields the check-in form shows, and who is emailed about each check-in.
                    Route::middleware(['throttle:30,1', PermissionMiddleware::using(Permission::EditCheckInOptions)])
                        ->patch('/check-ins/options', [DashboardLocationCheckInOptionsController::class, 'update'])
                        ->name('locations.checkInOptions.update');

                    // How far ahead and how close to closing drivers can book, and who is emailed about each booking.
                    Route::middleware(['throttle:30,1', PermissionMiddleware::using(Permission::EditAppointmentOptions)])
                        ->patch('/appointments/options', [DashboardLocationAppointmentOptionsController::class, 'update'])
                        ->name('locations.appointmentOptions.update');

                    // Attaching decides which locations a location-scoped user sees.
                    Route::middleware(['throttle:30,1', PermissionMiddleware::using(Permission::ManageLocationUsers)])->group(function (): void {
                        Route::post('/users', [DashboardLocationUserController::class, 'store'])->name('locations.users.store');
                        Route::delete('/users/{userUuid}', [DashboardLocationUserController::class, 'destroy'])
                            ->whereUuid('userUuid')
                            ->name('locations.users.destroy');
                    });
                });

                // Outside the section group: a deleted location has no page to look up.
                Route::middleware(['throttle:30,1', PermissionMiddleware::using(Permission::RestoreLocations)])
                    ->post('/locations/{uuid}/restore', DashboardLocationRestoreController::class)
                    ->whereUuid('uuid')
                    ->name('locations.restore');
            });

            Route::middleware(PermissionMiddleware::using(Permission::ViewRolesAndPermissions))
                ->get('/roles', DashboardRolePermissionController::class)
                ->name('roles');
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
