<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Enums\Theme;
use App\Models\User;

use function Pest\Laravel\get;

function themeDriver(?Theme $theme = null): User
{
    $user = User::query()->create([
        'name' => 'John Driver',
        'email' => 'driver@example.com',
        'password' => 'secret-password',
        'email_verified_at' => now(),
    ])->assignRole(Role::Driver);

    if ($theme instanceof Theme) {
        $user->forceFill(['theme' => $theme])->save();
    }

    return $user;
}

/*
 * The dark class has to be on <html> in the server's response. Applying it after React hydrates
 * flashes a white page on every full page load for anyone using dark mode.
 */
it('renders the stored theme onto the html tag', function (Theme $theme): void {
    $response = $this->actingAs(themeDriver($theme))->get(route('account.settings'));

    expect($response->getContent())->toContain(sprintf('data-theme="%s"', $theme->value));
})->with([
    'light' => [Theme::Light],
    'dark' => [Theme::Dark],
    'system' => [Theme::System],
]);

/*
 * Absence is meaningful: it is what lets the inline script fall through to localStorage instead of
 * overriding a guest's choice, or a driver's explicit "system" with a stale cached value.
 */
it('omits the attribute when the driver has never chosen', function (): void {
    $response = $this->actingAs(themeDriver())->get(route('account.settings'));

    expect($response->getContent())->not->toContain('data-theme=');
});

it('omits the attribute for guests', function (): void {
    $response = get(route('home'));

    expect($response->getContent())->not->toContain('data-theme=');
});

it('marks authenticated responses as worth persisting theme changes back to', function (): void {
    expect($this->actingAs(themeDriver())->get(route('account.settings'))->getContent())
        ->toContain('data-theme-persist');
});

/*
 * Public pages too, so a signed-in driver flipping the navbar toggle on the home page still has it
 * written to their account rather than only to this browser.
 */
it('marks authenticated responses on public pages as well', function (): void {
    expect($this->actingAs(themeDriver())->get(route('home'))->getContent())
        ->toContain('data-theme-persist');
});

it('leaves guests to localStorage, with nothing to persist to', function (): void {
    expect(get(route('home'))->getContent())
        ->not->toContain('data-theme-persist');
});

it('resolves the theme before first paint', function (): void {
    $content = $this->actingAs(themeDriver(Theme::Dark))->get(route('account.settings'))->getContent();

    // The script has to run inline in the head, ahead of the bundle, or it cannot beat the paint.
    expect($content)->toContain('prefers-color-scheme: dark')
        ->and(mb_strpos($content, 'prefers-color-scheme: dark'))
        ->toBeLessThan(mb_strpos($content, '</head>'));
});
