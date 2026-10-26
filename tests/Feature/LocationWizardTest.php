<?php

declare(strict_types=1);

use App\Enums\RecordHistoryEvent;
use App\Enums\Role;
use App\Enums\UserHistoryEvent;
use App\Models\Address;
use App\Models\Location;
use App\Models\User;
use App\Models\UserHistory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;

/*
 * The add location wizard: each step saved to the session draft, and the
 * location created from the finished draft.
 */

function wizardAdmin(): User
{
    return User::factory()->withRole(Role::Admin)->create(['name' => 'Ada Admin']);
}

/**
 * Answers the Census geocoder with one match, or none, and timeapi.io with
 * the given timezone, or a server error when null.
 */
function fakeWizardLookups(bool $match = true, ?string $timezone = 'America/Chicago'): void
{
    Http::fake([
        'geocoding.geo.census.gov/geocoder/locations/address*' => Http::response(['result' => [
            'addressMatches' => $match ? [['coordinates' => ['x' => -95.3698, 'y' => 29.7604]]] : [],
        ]]),
        'timeapi.io/api/TimeZone/coordinate*' => $timezone === null
            ? Http::response(status: 500)
            : Http::response(['timeZone' => $timezone]),
    ]);
}

/**
 * A week as the hours editor sends it: Mon-Fri 07:00-17:00, Saturday
 * mornings, closed Sunday.
 *
 * @return list<array{is_open: bool, open_time: string|null, close_time: string|null}>
 */
function wizardWeek(): array
{
    return array_map(fn (int $day): array => match ($day) {
        0 => ['is_open' => false, 'open_time' => null, 'close_time' => null],
        6 => ['is_open' => true, 'open_time' => '08:00', 'close_time' => '12:00'],
        default => ['is_open' => true, 'open_time' => '07:00', 'close_time' => '17:00'],
    }, range(0, 6));
}

/**
 * @return array<string, array<string, mixed>>
 */
function wizardAnswers(): array
{
    return [
        'details' => ['name' => 'North Dock Houston', 'email' => 'dock@example.com', 'phone' => '(713) 555-0100', 'phone_ext' => ''],
        'address' => ['street1' => '1 Main St', 'street2' => '', 'city' => 'Houston', 'state' => 'tx', 'zip_code' => '77002', 'zip_code_4' => '1234', 'country' => 'US'],
        'placement' => ['timezone' => 'America/Chicago', 'max_distance_allowed' => 10],
        'features' => ['is_checkins_enabled' => true, 'is_appointments_enabled' => false],
        'hours' => ['checkins' => wizardWeek()],
        'users' => ['users' => []],
    ];
}

/**
 * Saves every step up to and including the given one.
 *
 * @param  array<string, array<string, mixed>>  $overrides  Answers to change, keyed by step.
 */
function answerWizardThrough(string $last, array $overrides = []): void
{
    foreach (wizardAnswers() as $step => $answers) {
        test()->post(route("dashboard.locations.create.$step"), [...$answers, ...($overrides[$step] ?? [])])
            ->assertSessionHasNoErrors();

        if ($step === $last) {
            return;
        }
    }
}

function wizardStep(string $step): TestResponse
{
    return test()->get(route('dashboard.locations.create.step', ['step' => $step]));
}

it('keeps the wizard from staff without the create locations permission', function (): void {
    $this->actingAs(User::factory()->withRole(Role::ShippingManager)->create());

    wizardStep('details')->assertForbidden();
    $this->post(route('dashboard.locations.create.details'), wizardAnswers()['details'])->assertForbidden();
    $this->post(route('dashboard.locations.store'))->assertForbidden();
});

it('walks each step to the next and keeps the answers in the draft', function (): void {
    fakeWizardLookups();
    $this->actingAs(wizardAdmin());

    $this->get(route('dashboard.locations.create'))
        ->assertRedirect(route('dashboard.locations.create.step', ['step' => 'details']));

    $this->post(route('dashboard.locations.create.details'), wizardAnswers()['details'])
        ->assertRedirect(route('dashboard.locations.create.step', ['step' => 'address']));

    $this->post(route('dashboard.locations.create.address'), wizardAnswers()['address'])
        ->assertRedirect(route('dashboard.locations.create.step', ['step' => 'placement']));

    wizardStep('placement')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('Dashboard/Locations/Create')
        ->where('step', 'placement')
        ->where('draft.details.name', 'North Dock Houston')
        ->where('draft.address.state', 'TX')
        ->where('draft.address.latitude', 29.7604)
        ->where('draft.address.longitude', -95.3698)
        ->where('draft.address.timezone', 'America/Chicago')
        ->where('steps.0.completed', true)
        ->where('steps.2.completed', false)
        ->where('defaultMaxDistance', 5)
        ->has('timezones'));
});

it('sends a step opened early back to the first unsaved one', function (): void {
    $this->actingAs(wizardAdmin());

    $this->post(route('dashboard.locations.create.details'), wizardAnswers()['details']);

    wizardStep('review')->assertRedirect(route('dashboard.locations.create.step', ['step' => 'address']));
    $this->post(route('dashboard.locations.store'))->assertRedirect(route('dashboard.locations.create.step', ['step' => 'address']));

    expect(Location::query()->count())->toBe(0);
});

it('returns to review after a step of a finished draft is edited', function (): void {
    fakeWizardLookups();
    $this->actingAs(wizardAdmin());
    answerWizardThrough('users');

    $this->post(route('dashboard.locations.create.details'), [...wizardAnswers()['details'], 'name' => 'South Dock'])
        ->assertRedirect(route('dashboard.locations.create.step', ['step' => 'review']));
});

it('asks for the map step again once the address changes', function (): void {
    fakeWizardLookups();
    $this->actingAs(wizardAdmin());
    answerWizardThrough('users');

    $this->post(route('dashboard.locations.create.address'), [...wizardAnswers()['address'], 'street1' => '2 Main St'])
        ->assertRedirect(route('dashboard.locations.create.step', ['step' => 'placement']));
});

it('refuses an address the geocoder cannot find or reach', function (Closure $fake, string $message): void {
    $fake();
    $this->actingAs(wizardAdmin());
    answerWizardThrough('details');

    $this->post(route('dashboard.locations.create.address'), wizardAnswers()['address'])
        ->assertSessionHasErrors(['street1' => $message]);

    expect(session('locationWizard.steps.address'))->toBeNull();
})->with([
    'no match' => [fn () => fakeWizardLookups(match: false), 'This address could not be found. Please check the street, city, state and zip code.'],
    'census down' => [fn () => Http::fake(['geocoding.geo.census.gov/*' => Http::response(status: 503)]), 'The address could not be verified right now. Please try again.'],
]);

it('leaves the timezone for the admin to choose when the lookup fails or is outside the US', function (?string $timezone): void {
    fakeWizardLookups(timezone: $timezone);
    $this->actingAs(wizardAdmin());
    answerWizardThrough('address');

    expect(session('locationWizard.steps.address.timezone'))->toBeNull()
        ->and(session('locationWizard.steps.address.latitude'))->toBe(29.7604);
})->with([
    'lookup down' => [null],
    'not a US zone' => ['Europe/London'],
]);

it('sends the coordinates the geocoder found to the timezone lookup', function (): void {
    fakeWizardLookups();
    $this->actingAs(wizardAdmin());
    answerWizardThrough('address');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'timeapi.io')
        && $request['latitude'] === 29.7604
        && $request['longitude'] === -95.3698);
});

it('refuses an address another location already has', function (): void {
    fakeWizardLookups();
    Address::factory()->create(['street1' => '1 Main St', 'city' => 'Houston', 'state' => 'TX', 'zip_code' => '77002']);
    $this->actingAs(wizardAdmin());
    answerWizardThrough('details');

    $this->post(route('dashboard.locations.create.address'), wizardAnswers()['address'])
        ->assertSessionHasErrors(['street1' => 'Another location already has this address.']);

    Http::assertNothingSent();
});

it('does not take an abbreviation, since one is always made up', function (): void {
    $this->actingAs(wizardAdmin())
        ->post(route('dashboard.locations.create.details'), [...wizardAnswers()['details'], 'abbreviation' => 'NDH'])
        ->assertSessionHasErrors('abbreviation');
});

it('requires the details, a US address and a listed timezone', function (): void {
    fakeWizardLookups();
    $this->actingAs(wizardAdmin());

    $this->post(route('dashboard.locations.create.details'), [])
        ->assertSessionHasErrors(['name', 'email', 'phone']);

    answerWizardThrough('details');

    $this->post(route('dashboard.locations.create.address'), [...wizardAnswers()['address'], 'country' => 'CA'])
        ->assertSessionHasErrors(['country' => 'Only US addresses are supported.']);

    answerWizardThrough('address');

    $this->post(route('dashboard.locations.create.placement'), ['timezone' => 'Europe/London', 'max_distance_allowed' => 10])
        ->assertSessionHasErrors(['timezone' => 'Choose a timezone from the list.']);

    answerWizardThrough('placement');

    $this->post(route('dashboard.locations.create.features'), ['is_checkins_enabled' => false, 'is_appointments_enabled' => false])
        ->assertSessionHasErrors(['is_checkins_enabled' => 'Turn on check-ins, appointments, or both.']);
});

it('asks for the week of each feature turned on, and only those', function (): void {
    fakeWizardLookups();
    $this->actingAs(wizardAdmin());
    answerWizardThrough('features');

    wizardStep('hours')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('defaultHours.0', ['is_open' => false, 'open_time' => '07:00', 'close_time' => '17:00'])
        ->where('defaultHours.1', ['is_open' => true, 'open_time' => '07:00', 'close_time' => '17:00']));

    $this->post(route('dashboard.locations.create.hours'), [])
        ->assertSessionHasErrors(['checkins']);

    $this->post(route('dashboard.locations.create.hours'), ['checkins' => wizardWeek(), 'appointments' => wizardWeek()])
        ->assertSessionHasErrors(['appointments']);
});

it('refuses a day that closes before it opens', function (): void {
    fakeWizardLookups();
    $this->actingAs(wizardAdmin());
    answerWizardThrough('features');

    $week = wizardWeek();
    $week[6] = ['is_open' => true, 'open_time' => '12:00', 'close_time' => '08:00'];

    $this->post(route('dashboard.locations.create.hours'), ['checkins' => $week])
        ->assertSessionHasErrors(['checkins.6.close_time' => 'Must be after the opening time.']);
});

it('asks for the hours again once the features change', function (): void {
    fakeWizardLookups();
    $this->actingAs(wizardAdmin());
    answerWizardThrough('users');

    $this->post(route('dashboard.locations.create.features'), ['is_checkins_enabled' => true, 'is_appointments_enabled' => true])
        ->assertRedirect(route('dashboard.locations.create.step', ['step' => 'hours']));
});

it('only lets location-scoped staff be added', function (): void {
    $this->actingAs(wizardAdmin());
    $sales = User::factory()->withRole(Role::Sales)->create();

    $this->post(route('dashboard.locations.create.users'), ['users' => [$sales->uuid]])
        ->assertSessionHasErrors(['users' => 'Only staff limited to their own locations can be added.']);
});

it('lists only location-scoped staff to pick from', function (): void {
    fakeWizardLookups();
    $this->actingAs(wizardAdmin());
    $shipping = User::factory()->withRole(Role::Shipping)->create();
    User::factory()->withRole(Role::Sales)->create();
    answerWizardThrough('hours');

    wizardStep('users')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('staff', 1)
        ->where('staff.0.uuid', $shipping->uuid));
});

it('creates the location with its address, hours, users and history', function (): void {
    fakeWizardLookups();
    $admin = wizardAdmin();
    $shipping = User::factory()->withRole(Role::Shipping)->create(['name' => 'Sam']);
    $this->actingAs($admin);
    answerWizardThrough('users', [
        'details' => ['phone_ext' => '12'],
        'users' => ['users' => [$shipping->uuid]],
    ]);

    $response = $this->post(route('dashboard.locations.store'));

    $location = Location::query()->with('address')->sole();

    $response->assertRedirect(route('dashboard.locations.show', ['uuid' => $location->uuid]))
        ->assertInertiaFlash('toast.message', 'North Dock Houston was created.');

    expect($location->only('name', 'abbreviation', 'email', 'phone', 'phone_ext', 'timezone', 'max_distance_allowed', 'is_active', 'is_checkins_enabled', 'is_appointments_enabled'))
        ->toBe([
            'name' => 'North Dock Houston',
            'abbreviation' => 'NDH',
            'email' => 'dock@example.com',
            'phone' => '(713) 555-0100',
            'phone_ext' => '12',
            'timezone' => 'America/Chicago',
            'max_distance_allowed' => 10,
            'is_active' => true,
            'is_checkins_enabled' => true,
            'is_appointments_enabled' => false,
        ])
        ->and($location->address->only('street1', 'city', 'state', 'zip_code', 'zip_code_4', 'country', 'latitude', 'longitude'))
        ->toBe([
            'street1' => '1 Main St',
            'city' => 'Houston',
            'state' => 'TX',
            'zip_code' => '77002',
            'zip_code_4' => '1234',
            'country' => 'US',
            'latitude' => 29.7604,
            'longitude' => -95.3698,
        ])
        ->and($location->checkinSchedule()->orderBy('day_of_week')->get(['day_of_week', 'open_time', 'close_time'])->toArray())
        ->toBe([
            ...array_map(fn (int $day): array => ['day_of_week' => $day, 'open_time' => '07:00:00', 'close_time' => '17:00:00'], [1, 2, 3, 4, 5]),
            ['day_of_week' => 6, 'open_time' => '08:00:00', 'close_time' => '12:00:00'],
        ])
        ->and($location->appointmentSchedule()->count())->toBe(0)
        ->and($location->users()->pluck('users.id')->all())->toBe([$shipping->id])
        ->and($location->history()->pluck('event')->all())->toEqualCanonicalizing([RecordHistoryEvent::Created, RecordHistoryEvent::LocationUserAttached])
        ->and(UserHistory::query()->where('user_id', $admin->id)->pluck('event')->all())
        ->toBe([UserHistoryEvent::LocationCreated, UserHistoryEvent::LocationUserAttached])
        ->and(session('locationWizard'))->toBeNull();
});

it('gives each feature turned on its own hours, a full day stored to the last second', function (): void {
    fakeWizardLookups();
    $this->actingAs(wizardAdmin());
    $allDay = array_fill(0, 7, ['is_open' => true, 'open_time' => '00:00', 'close_time' => '23:59']);
    answerWizardThrough('users', [
        'features' => ['is_checkins_enabled' => true, 'is_appointments_enabled' => true],
        'hours' => ['checkins' => wizardWeek(), 'appointments' => $allDay],
    ]);

    $this->post(route('dashboard.locations.store'))->assertSessionHasNoErrors();

    $location = Location::query()->sole();

    expect($location->checkinSchedule()->count())->toBe(6)
        ->and($location->appointmentSchedule()->orderBy('day_of_week')->get(['day_of_week', 'open_time', 'close_time'])->toArray())
        ->toBe(array_map(fn (int $day): array => ['day_of_week' => $day, 'open_time' => '00:00:00', 'close_time' => '23:59:59'], range(0, 6)));
});

it('creates a location with nobody added, visible to admins only', function (): void {
    fakeWizardLookups();
    $this->actingAs(wizardAdmin());
    $shipping = User::factory()->withRole(Role::Shipping)->create();
    answerWizardThrough('users');

    $this->post(route('dashboard.locations.store'))->assertSessionHasNoErrors();

    $location = Location::query()->sole();

    expect($location->users()->count())->toBe(0)
        ->and($shipping->dashboardLocationIds())->toBe([]);
});

it('makes up a free abbreviation from the name', function (): void {
    fakeWizardLookups();
    Location::factory()->create(['abbreviation' => 'NDH']);
    $this->actingAs(wizardAdmin());
    answerWizardThrough('users');

    wizardStep('review')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('abbreviationPreview', 'NDH2'));

    $this->post(route('dashboard.locations.store'));

    expect(Location::query()->where('name', 'North Dock Houston')->value('abbreviation'))->toBe('NDH2');
});

it('throws the draft away on discard', function (): void {
    $this->actingAs(wizardAdmin());
    answerWizardThrough('details');

    $this->delete(route('dashboard.locations.create.cancel'))
        ->assertRedirect(route('dashboard.locations'));

    expect(session('locationWizard'))->toBeNull();
});
