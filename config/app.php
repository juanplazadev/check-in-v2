<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Deployed Commit
    |--------------------------------------------------------------------------
    |
    | The Git commit SHA baked into the image at build time (GIT_COMMIT build
    | arg). Read through config so it survives `config:cache` — env() outside a
    | config file returns null once the config is cached. Null in local dev.
    |
    */

    'commit' => env('GIT_COMMIT'),

    /*
    |--------------------------------------------------------------------------
    | Seeded Admin Password
    |--------------------------------------------------------------------------
    |
    | The password given to the known-credential admin account created by
    | UserSeeder. Read through config for the same reason as the commit above:
    | the seeder runs after `php artisan optimize` on deploy, and env() outside
    | a config file returns null once the config is cached. Falls back to
    | "password" for local development; UserSeeder refuses to run in production.
    |
    */

    'seed_admin_password' => env('SEED_ADMIN_PASSWORD') ?: 'password',

    /*
    |--------------------------------------------------------------------------
    | Running Under Sail
    |--------------------------------------------------------------------------
    |
    | Set by Sail's containers. Read through config for the same reason as the
    | commit above. Decides which processes `artisan dev` starts.
    |
    */

    'sail' => (bool) env('LARAVEL_SAIL', false),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => env('APP_TIMEZONE', 'UTC'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    'locales' => [
        'en',
        'es',
        'fr',
    ],

    'locales_labels' => [
        'en' => 'English',
        'es' => 'Español',
        'fr' => 'Français',
    ],

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', '')),
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Encryption Key
    |--------------------------------------------------------------------------
    |
    | A dedicated key used only for encrypting sensitive data at rest (via the
    | App\Casts\Encrypted cast). Keeping this separate from APP_KEY lets us
    | rotate APP_KEY (cookies, sessions, signed URLs) without re-encrypting
    | stored columns. Rotate this key on its own schedule, moving the old key
    | into DB_ENCRYPTION_PREVIOUS_KEYS until every row is re-encrypted.
    |
    */

    'db_encryption_key' => env('DB_ENCRYPTION_KEY'),

    'db_encryption_previous_keys' => [
        ...array_filter(
            explode(',', (string) env('DB_ENCRYPTION_PREVIOUS_KEYS', '')),
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Location Closing Soon Threshold
    |--------------------------------------------------------------------------
    |
    | Defines how many minutes before closing a location is considered
    | "closing soon". When a location falls within this threshold, a
    | "Closing soon" indicator will be displayed in the check-in UI.
    |
    | Default: 30 minutes
    |
    */

    'location_closing_soon_threshold' => 30,

    /*
    |--------------------------------------------------------------------------
    | User Coordinates Session Time-to-Live
    |--------------------------------------------------------------------------
    |
    | Specifies how long (in seconds) the user's coordinates are stored
    | in the server-side session. After this duration, the coordinates
    | will expire and may need to be re-acquired.
    |
    | Default: 60 seconds * 5 (5 minutes)
    |
    */

    'user_coordinates_session_ttl' => 60 * 5,

    /*
    |--------------------------------------------------------------------------
    | User Coordinates Browser Time-to-Live
    |--------------------------------------------------------------------------
    |
    | Specifies how long (in minutes) the user's coordinates are cached
    | in the browser (e.g., localStorage). Once expired, the browser
    | should request fresh location data.
    |
    | Default: 5 minutes
    |
    */

    'user_coordinates_browser_ttl' => 5,

    /*
    |--------------------------------------------------------------------------
    | Check-In Gate Pass Time-to-Live
    |--------------------------------------------------------------------------
    |
    | How long (in seconds) a driver has to complete and submit the
    | check-in form after clearing the proximity gate. The gate pass is
    | issued only once the proximity check has passed and is bound to a
    | specific location, so this can be generous without weakening the
    | "is the driver actually here?" guarantee enforced by the coordinate
    | freshness window above.
    |
    | Default: 60 seconds * 20 (20 minutes)
    |
    */

    'checkin_gate_pass_ttl' => 60 * 20,

    /*
    |--------------------------------------------------------------------------
    | Registration Window
    |--------------------------------------------------------------------------
    |
    | How long (in seconds) a driver may register an account after completing a
    | check-in or booking an appointment. Registration is only permitted inside
    | this short window; the flag is cleared once used or expired.
    |
    | Default: 60 seconds * 15 (15 minutes)
    |
    */

    'registration_window_ttl' => 60 * 15,

    /*
    |--------------------------------------------------------------------------
    | Appointment Reminder Hour
    |--------------------------------------------------------------------------
    |
    | The hour (0-23) on the day before an appointment at which the driver's
    | reminder goes out, resolved in the location's own timezone rather than
    | the app's. A fixed local hour is what the settings copy promises ("a
    | reminder the day before") and it keeps the message out of the middle of
    | the night, which a plain "24 hours before the slot" rule would not for
    | early-morning appointments.
    |
    | Default: 17 (5pm the day before, local to the location)
    |
    */

    'appointment_reminder_hour' => (int) env('APPOINTMENT_REMINDER_HOUR', 17),

    /*
    |--------------------------------------------------------------------------
    | User Location Distances Time-to-Live
    |--------------------------------------------------------------------------
    |
    | How long (in minutes) the computed location-distance list is cached
    | per user. The cache key includes the exact coordinates and session,
    | so it self-invalidates when the driver moves; this TTL just bounds
    | how long an unchanged result is reused. Keep it aligned with the
    | coordinate freshness window above.
    |
    | Default: 5 minutes
    |
    */

    'user_location_distances_ttl' => 5,

    /*
    |--------------------------------------------------------------------------
    | Appointment Location Context Time-to-Live
    |--------------------------------------------------------------------------
    |
    | How long (in seconds) the selected appointment location context is
    | retained in the server-side session between the appointment gate and
    | the booking form.
    |
    | Default: 60 seconds * 2 (2 minutes)
    |
    */

    'user_appointment_location_context_ttl' => 60 * 2,

    /*
    |--------------------------------------------------------------------------
    | Add Location Wizard
    |--------------------------------------------------------------------------
    |
    | How long (in seconds) an unfinished add location wizard is kept in the
    | session after its last saved step, the check-in radius a new location
    | starts with, and the week the wizard's hours step is filled in with
    | (Carbon day numbers, 0 = Sunday). The admin can change both in the
    | wizard and on the location's pages afterwards.
    |
    | Default TTL: 60 seconds * 60 (1 hour)
    |
    */

    'location_wizard_ttl' => 60 * 60,

    'location_default_max_distance' => 5,

    'location_default_hours' => [
        'days' => [1, 2, 3, 4, 5],
        'open_time' => '07:00:00',
        'close_time' => '17:00:00',
    ],

    /*
    |--------------------------------------------------------------------------
    | Public Contact Details
    |--------------------------------------------------------------------------
    |
    | The phone number and email shown on the public contact page. Set these
    | via the CONTACT_PHONE and CONTACT_EMAIL environment variables.
    |
    */

    'contact_phone' => env('CONTACT_PHONE', '(800) 555-0100'),

    'contact_email' => env('CONTACT_EMAIL', 'dispatch@martorifarms.com'),

    /*
    |--------------------------------------------------------------------------
    | Weekly Schedule Cache
    |--------------------------------------------------------------------------
    |
    | How long (in minutes) the public weekly schedule payload is cached. The
    | cache is flushed immediately whenever schedule data changes, so this TTL
    | only bounds how stale the live "open now" / "today" indicators can get.
    |
    */

    'schedule_cache_ttl' => (int) env('SCHEDULE_CACHE_TTL', 10),

    /*
    |--------------------------------------------------------------------------
    | Appointment Booking Safeguards
    |--------------------------------------------------------------------------
    |
    | Hard limits and defaults that clamp per-location appointment booking
    | configuration. These protect the booking UX from careless or malicious
    | admin values by enforcing sane defaults, caps, and an allowlist for the
    | slot interval granularity.
    |
    */

    'appointment_booking' => [
        'max_days_ahead' => 7,
        'slot_interval_minutes' => [
            'default' => 15,
            'allowed' => [10, 15, 30, 60],
        ],
        'lead_time_minutes' => ['default' => 60, 'max' => 480],
        'buffer_before_close_minutes' => ['default' => 30, 'max' => 480],
    ],

    /*
    |--------------------------------------------------------------------------
    | Truck Colors
    |--------------------------------------------------------------------------
    |
    | Allowlist of truck colors offered in the check-in form's optional color
    | dropdown. The order here is the order shown in the UI and the backend
    | validation allowlist. Display labels are translated per-locale in
    | lang/{locale}/messages.php under checkInForm.truckColors — keep those
    | keys in sync with the slugs below.
    |
    */

    'truck_colors' => [
        'white',
        'black',
        'gray',
        'red',
        'orange',
        'yellow',
        'green',
        'blue',
        'purple',
        'brown',
        'beige',
        'maroon',
        'gold',
        'multicolor',
        'other',
    ],
];
