<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'census_geocoder' => [
        'url' => env('CENSUS_GEOCODER_URL', 'https://geocoding.geo.census.gov/geocoder'),
        'benchmark' => env('CENSUS_GEOCODER_BENCHMARK', 'Public_AR_Current'),
    ],

    'timezone_lookup' => [
        'url' => env('TIMEZONE_LOOKUP_URL', 'https://timeapi.io/api'),
    ],

    /*
     * The Sentry REST API behind the admin errors and deployments pages. The
     * token is an internal integration's with org:read, project:read,
     * project:releases and event:read. The API exposes no plan quota, so the
     * monthly error quota and the day it resets are set here.
     */
    'sentry_api' => [
        'url' => env('SENTRY_API_URL', 'https://sentry.io'),
        'token' => env('SENTRY_AUTH_TOKEN'),
        'org' => env('SENTRY_ORG'),
        'project' => env('SENTRY_PROJECT'),
        'error_quota' => (int) env('SENTRY_ERROR_QUOTA', 5000),
        'billing_day' => (int) env('SENTRY_BILLING_DAY', 1),
    ],

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
