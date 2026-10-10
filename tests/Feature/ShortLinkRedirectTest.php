<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\ShortLink;
use App\Models\User;
use Illuminate\Support\Facades\Config;

it('expands a code to the check-in record', function (): void {
    $checkIn = CheckIn::factory()->create();
    $checkIn->shortUrl();

    $code = $checkIn->shortLink()->firstOrFail()->code;

    $this->get('/r/'.$code)
        ->assertRedirect(route('account.history.checkIn', $checkIn->uuid));
});

it('expands a code to the appointment record', function (): void {
    $appointment = Appointment::factory()->create();
    $appointment->shortUrl();

    $code = $appointment->shortLink()->firstOrFail()->code;

    $this->get('/r/'.$code)
        ->assertRedirect(route('account.history.appointment', $appointment->uuid));
});

/*
 * A link-only client host serves the code at the root, so both shapes have to
 * resolve regardless of how shortlink.prefix happens to be set. Changing the
 * prefix must not strand links already sitting in someone's messages.
 */
it('resolves the bare form as well as the prefixed one', function (): void {
    $checkIn = CheckIn::factory()->create();
    $checkIn->shortUrl();

    $code = $checkIn->shortLink()->firstOrFail()->code;

    $this->get('/'.$code)
        ->assertRedirect(route('account.history.checkIn', $checkIn->uuid));
});

it('404s on an unknown code', function (): void {
    $this->get('/r/QRSTUVW')->assertNotFound();
});

it('404s when the record behind the code is gone', function (): void {
    $checkIn = CheckIn::factory()->create();
    $checkIn->shortUrl();

    $code = $checkIn->shortLink()->firstOrFail()->code;

    $checkIn->delete();

    $this->get('/r/'.$code)->assertNotFound();
});

/*
 * The bare route matches seven uppercase characters and is registered after
 * everything in web.php, so it must not swallow a real page, nor anything that
 * only looks close to a code.
 */
it('does not swallow a real page', function (): void {
    $this->get('/contact')->assertOk();
});

it('leaves shapes that are not codes to 404', function (string $path): void {
    $this->get($path)->assertNotFound();
})->with([
    'too short' => ['/QRSTUV'],
    'too long' => ['/QRSTUVWX'],
    'lowercase' => ['/qrstuvw'],
    'ambiguous characters' => ['/QRSTUV0'],
]);

/*
 * The security property the whole scheme rests on: the code shortens the URL
 * and nothing else. A forwarded text still lands on a login, not a record.
 */
it('does not expose the record to a signed-out visitor', function (): void {
    $checkIn = CheckIn::factory()->create();
    $checkIn->shortUrl();

    $code = $checkIn->shortLink()->firstOrFail()->code;

    $this->get('/r/'.$code)
        ->assertRedirect(route('account.history.checkIn', $checkIn->uuid));

    $this->get(route('account.history.checkIn', $checkIn->uuid))
        ->assertRedirect(route('login'));
});

it('lands the driver on the record after signing in', function (): void {
    $driver = User::query()->create([
        'name' => 'John Driver',
        'email' => 'driver@example.com',
        'password' => 'secret-password',
        'email_verified_at' => now(),
    ])->assignRole(Role::Driver);
    $checkIn = CheckIn::factory()->forUser($driver)->create();
    $checkIn->shortUrl();

    $code = $checkIn->shortLink()->firstOrFail()->code;

    $target = route('account.history.checkIn', $checkIn->uuid);

    $this->get('/r/'.$code)->assertRedirect($target);

    // Following it signed out stores the intended URL, which Fortify's login
    // response then honours.
    $this->get($target)->assertRedirect(route('login'));

    $this->actingAs($driver)->get($target)->assertOk();
});

it('reuses one code per record rather than minting a new one each send', function (): void {
    $checkIn = CheckIn::factory()->create();

    $first = $checkIn->shortUrl();
    $second = $checkIn->shortUrl();

    expect($first)->toBe($second)
        ->and(ShortLink::query()->count())->toBe(1);
});

it('counts a visit', function (): void {
    $checkIn = CheckIn::factory()->create();
    $checkIn->shortUrl();

    $link = $checkIn->shortLink()->firstOrFail();

    expect($link->visits)->toBe(0)
        ->and($link->last_visited_at)->toBeNull();

    $this->get('/r/'.$link->code);

    $link->refresh();

    expect($link->visits)->toBe(1)
        ->and($link->last_visited_at)->not->toBeNull();
});

it('answers on a client link domain without any route configuration', function (): void {
    Config::set('shortlink.host', 'go.dockin.app');
    Config::set('shortlink.prefix', '');

    $checkIn = CheckIn::factory()->create();
    $url = $checkIn->shortUrl();
    $code = $checkIn->shortLink()->firstOrFail()->code;

    expect($url)->toBe('https://go.dockin.app/'.$code);

    // Resolution carries no domain constraint, so the same code answers on
    // whatever host reaches the application.
    $this->get('https://go.dockin.app/'.$code)
        ->assertRedirect(route('account.history.checkIn', $checkIn->uuid));
});
