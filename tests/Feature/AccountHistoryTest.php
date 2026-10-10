<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\get;

function historyPageDriver(string $email = 'driver@example.com'): User
{
    return User::query()->create([
        'name' => 'John Driver',
        'email' => $email,
        'password' => 'secret-password',
        'email_verified_at' => now(),
    ])->assignRole(Role::Driver);
}

function phoenixLocation(): Location
{
    return Location::factory()->create(['timezone' => 'America/Phoenix']);
}

/**
 * Headers for a partial Inertia visit. The asset version is resolved rather
 * than hardcoded, since it is a build hash and a mismatch 409s.
 *
 * @return array<string, string>
 */
function partialVisitHeaders(string $only, ?string $reset = null): array
{
    return array_filter([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) resolve(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'Account/History',
        'X-Inertia-Partial-Data' => $only,
        'X-Inertia-Reset' => $reset,
    ]);
}

it('redirects guests to the login page', function (): void {
    get(route('account.history'))->assertRedirect(route('login'));
});

it('defaults to the check-ins tab over all time', function (): void {
    $driver = historyPageDriver();

    $this->actingAs($driver)
        ->get(route('account.history'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Account/History')
            ->where('filters.tab', 'check-ins')
            ->where('filters.period', 'all')
            ->where('history.data', [])
            ->where('history.hasMore', false));
});

it('ships the history translation bundles and nav label', function (string $locale, string $historyTab): void {
    $driver = historyPageDriver();

    $this->actingAs($driver)
        ->withSession(['locale' => $locale])
        ->get(route('account.history'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('translations.accountHistory.title')
            ->has('translations.accountHistory.tabCheckIns')
            ->has('translations.accountHistory.statusCheckedIn')
            ->where('translations.accountNav.history', $historyTab));
})->with([
    'english' => ['en', 'History'],
    'spanish' => ['es', 'Historial'],
    'french' => ['fr', 'Historique'],
]);

it('ships the record translation bundle on the detail pages', function (): void {
    $location = phoenixLocation();
    $driver = historyPageDriver();
    $checkIn = CheckIn::factory()->forUser($driver)->atLocation($location)->create();

    $this->actingAs($driver)
        ->get(route('account.history.checkIn', $checkIn))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('translations.accountHistoryRecord.checkInTitle')
            ->has('translations.accountHistoryRecord.viewPdf')
            ->has('translations.accountHistoryRecord.emailCopy')
            ->has('translations.accountNav.history'));
});

it('lists only the driver own check-ins', function (): void {
    $location = phoenixLocation();
    $driver = historyPageDriver();
    $other = historyPageDriver('other@example.com');

    $mine = CheckIn::factory()->forUser($driver)->atLocation($location)->create();
    CheckIn::factory()->forUser($other)->atLocation($location)->create();

    $this->actingAs($driver)
        ->get(route('account.history'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('history.data', 1)
            ->where('history.data.0.referenceNumber', $mine->reference_number));
});

it('switches to the appointments tab', function (): void {
    $location = phoenixLocation();
    $driver = historyPageDriver();

    CheckIn::factory()->forUser($driver)->atLocation($location)->create();
    $appointment = Appointment::factory()->forUser($driver)->atLocation($location)
        ->scheduledFor(Date::now()->subDays(3))->create();

    $this->actingAs($driver)
        ->get(route('account.history', ['tab' => 'appointments']))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('filters.tab', 'appointments')
            ->has('history.data', 1)
            ->where('history.data.0.referenceNumber', $appointment->reference_number));
});

it('formats dates in the location timezone rather than utc', function (): void {
    // 03:30 UTC on the 12th is 20:30 on the 11th in Phoenix (UTC-7).
    $location = phoenixLocation();
    $driver = historyPageDriver();

    Appointment::factory()->forUser($driver)->atLocation($location)
        ->scheduledFor(Date::parse('2026-03-12 03:30:00', 'UTC'))->create();

    $this->actingAs($driver)
        ->get(route('account.history', ['tab' => 'appointments']))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('history.data.0.date', 'Mar 11, 2026')
            ->where('history.data.0.time', '8:30 PM MST')
            ->where('history.data.0.day', '11'));
});

it('bounds the list by the selected period but keeps future appointments', function (): void {
    $location = phoenixLocation();
    $driver = historyPageDriver();

    Appointment::factory()->forUser($driver)->atLocation($location)
        ->scheduledFor(Date::now()->addDays(45))->create();
    Appointment::factory()->forUser($driver)->atLocation($location)
        ->scheduledFor(Date::now()->subDays(200))->create();

    $this->actingAs($driver)
        ->get(route('account.history', ['tab' => 'appointments', 'period' => '30d']))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('history.data', 1)
            ->where('history.data.0.isUpcoming', true));
});

it('reports whether more pages remain', function (): void {
    $location = phoenixLocation();
    $driver = historyPageDriver();

    foreach (range(1, 16) as $daysAgo) {
        CheckIn::factory()->forUser($driver)->atLocation($location)
            ->createdAt(Date::now()->subDays($daysAgo))->create();
    }

    $this->actingAs($driver)
        ->get(route('account.history'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('history.data', 15)
            ->where('history.currentPage', 1)
            ->where('history.hasMore', true));

    $this->actingAs($driver)
        ->get(route('account.history', ['page' => 2]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('history.data', 1)
            ->where('history.currentPage', 2)
            ->where('history.hasMore', false));
});

it('rejects filter values outside the allowed set', function (array $query): void {
    $driver = historyPageDriver();

    $this->actingAs($driver)
        ->get(route('account.history', $query))
        ->assertSessionHasErrors();
})->with([
    'unknown tab' => [['tab' => 'everything']],
    'unknown period' => [['period' => '7d']],
    'page below one' => [['page' => 0]],
    'page above the cap' => [['page' => 99999999]],
]);

it('marks the history rows as an appendable merge prop', function (): void {
    $driver = historyPageDriver();

    $response = $this->actingAs($driver)->get(
        route('account.history', ['page' => 2]),
        partialVisitHeaders('history'),
    );

    $response->assertSuccessful();

    expect($response->json('mergeProps'))->toBe(['history.data'])
        ->and($response->json('matchPropsOn'))->toBe(['history.data.key']);
});

it('drops the merge metadata when the client asks for a reset', function (): void {
    $driver = historyPageDriver();

    $response = $this->actingAs($driver)->get(
        route('account.history'),
        partialVisitHeaders('history', reset: 'history'),
    );

    $response->assertSuccessful();

    expect($response->json('mergeProps'))->toBeNull();
});
