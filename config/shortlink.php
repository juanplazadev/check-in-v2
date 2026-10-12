<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Link Host
    |--------------------------------------------------------------------------
    |
    | The host short URLs are built with. Null falls back to the host in
    | APP_URL, which is the right answer for local development and for a
    | deployment that has not been given a dedicated link domain yet.
    |
    | One deployment serves one client, so a client bringing their own domain
    | sets it here. Nothing else in the application reads this: the redirect
    | route answers on whatever host reaches it, so pointing a new domain at
    | the app is DNS and a Caddy host, not a code change.
    |
    */

    'host' => env('SHORTLINK_HOST'),

    /*
    |--------------------------------------------------------------------------
    | Path Prefix
    |--------------------------------------------------------------------------
    |
    | Set to an empty string when the host serves nothing but links, which puts
    | the code at the root and saves two characters. Keep the default when
    | links share a host with the application itself.
    |
    */

    'prefix' => env('SHORTLINK_PREFIX', 'r'),

    /*
    |--------------------------------------------------------------------------
    | Scheme
    |--------------------------------------------------------------------------
    |
    | Null follows APP_URL. Worth leaving alone unless a client's link domain
    | genuinely differs from the application's own scheme.
    |
    */

    'scheme' => env('SHORTLINK_SCHEME'),

];
