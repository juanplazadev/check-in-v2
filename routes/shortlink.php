<?php

declare(strict_types=1);

use App\Http\Controllers\ShortLinkController;
use Illuminate\Support\Facades\Route;

/*
 * The redirect behind a texted link.
 *
 * Registered outside the `web` group on purpose. Expanding a code needs no
 * session, no Inertia and no locale, and staying stateless avoids planting a
 * session cookie on a client-owned link domain. The auth gate lives on the
 * target page, which is where it belongs.
 *
 * No domain constraint either: the route answers on whatever host reaches the
 * application, so a client pointing their own domain at it is DNS and a Caddy
 * host rather than a code change. Only URL generation reads configuration,
 * in {@see \App\ShortLink\ShortLinkUrlGenerator}.
 *
 * Both shapes are always accepted, whatever `shortlink.prefix` happens to be,
 * so changing the prefix does not strand links already sitting in someone's
 * messages. The bare form is what a link-only host uses.
 */

// Excludes I, L, O, 0 and 1, matching the generator's alphabet. The pattern is
// tight enough that the bare form cannot shadow a real page: every route in
// web.php is lowercase, and this matches seven uppercase characters only.
$code = '[A-HJKMNP-Z2-9]{7}';

Route::get('/r/{code}', ShortLinkController::class)
    ->where('code', $code)
    ->name('shortlink');

Route::get('/{code}', ShortLinkController::class)
    ->where('code', $code)
    ->name('shortlink.bare');
