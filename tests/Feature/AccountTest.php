<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\CheckInStatus;
use App\Enums\Role;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\get;

it('redirects guests to the login page', function (): void {
    get(route('account'))->assertRedirect(route('login'));
});

it('renders the driver account for an authenticated user', function (): void {
    $user = User::query()->create([
        'name' => 'John Driver',
        'email' => 'john@example.com',
        'password' => 'secret-password',
        'email_verified_at' => now(),
    ])->assignRole(Role::Driver);

    $this->actingAs($user)
        ->get(route('account'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Account')
            ->where('nextAppointment', null)
            ->where('recentCheckIns', [])
            ->has('translations.account.checkInNow'));
});

it('lets admins into the account area', function (): void {
    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->get(route('account'))
        ->assertSuccessful();
});

it('forbids the account area to non-admin staff', function (Role $role, string $routeName): void {
    $this->actingAs(User::factory()->withRole($role)->create())
        ->get(route($routeName))
        ->assertForbidden();
})->with([Role::ShippingManager, Role::Shipping, Role::Sales])->with([
    'verified surface' => 'account',
    'pre-verification corridor' => 'account.profile',
]);

it('reports whether the driver has a license on file for the hub chips and nudges', function (): void {
    $without = User::query()->create([
        'name' => 'No License',
        'email' => 'nolicense@example.com',
        'password' => 'secret-password',
        'email_verified_at' => now(),
    ])->assignRole(Role::Driver);

    $this->actingAs($without)
        ->get(route('account'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('hasLicense', false)
            ->has('translations.accountNav.overview'));

    $with = User::query()->create([
        'name' => 'Has License',
        'email' => 'haslicense@example.com',
        'password' => 'secret-password',
        'email_verified_at' => now(),
        'drivers_license_number' => 'D1234567',
    ])->assignRole(Role::Driver);

    $this->actingAs($with)
        ->get(route('account'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('hasLicense', true));
});

it('flags an expired license so the overview can nudge the driver', function (): void {
    $expired = User::query()->create([
        'name' => 'Expired License',
        'email' => 'expired@example.com',
        'password' => 'secret-password',
        'email_verified_at' => now(),
        'drivers_license_number' => 'D1234567',
        'drivers_license_expiration_date' => now()->subDay()->format('Y-m-d'),
    ])->assignRole(Role::Driver);

    $this->actingAs($expired)
        ->get(route('account'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('hasLicense', true)
            ->where('licenseExpired', true));

    $valid = User::query()->create([
        'name' => 'Valid License',
        'email' => 'valid@example.com',
        'password' => 'secret-password',
        'email_verified_at' => now(),
        'drivers_license_number' => 'D7654321',
        'drivers_license_expiration_date' => now()->addYear()->format('Y-m-d'),
    ])->assignRole(Role::Driver);

    $this->actingAs($valid)
        ->get(route('account'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('hasLicense', true)
            ->where('licenseExpired', false));
});

it("surfaces the driver's next appointment and recent check-ins", function (): void {
    $user = User::query()->create([
        'name' => 'John Driver',
        'email' => 'john@example.com',
        'password' => 'secret-password',
        'email_verified_at' => now(),
    ])->assignRole(Role::Driver);
    $location = Location::factory()->create();

    $appointment = Appointment::factory()->create([
        'location_id' => $location->id,
        'user_id' => $user->id,
        'status' => AppointmentStatus::Scheduled,
        'scheduled_for' => now()->addDays(2),
    ]);

    CheckIn::factory()->create([
        'location_id' => $location->id,
        'user_id' => $user->id,
        'status' => CheckInStatus::Pending,
    ]);

    $this->actingAs($user)
        ->get(route('account'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Account')
            ->has('nextAppointment', fn (AssertableInertia $prop): AssertableInertia => $prop
                ->where('referenceNumber', $appointment->reference_number)
                ->where('locationName', $location->name)
                ->has('monthShort')
                ->has('day')
                ->has('time'))
            ->has('recentCheckIns', 1));
});

it('localises the next appointment month for the active locale', function (string $locale, string $monthShort): void {
    Date::setTestNow(Date::parse('2026-01-10 12:00:00', 'UTC'));

    $user = User::query()->create([
        'name' => 'Jane Driver',
        'email' => 'jane@example.com',
        'password' => 'secret-password',
        'email_verified_at' => now(),
    ])->assignRole(Role::Driver);
    $location = Location::factory()->create();

    Appointment::factory()->create([
        'location_id' => $location->id,
        'user_id' => $user->id,
        'status' => AppointmentStatus::Scheduled,
        'scheduled_for' => Date::parse('2026-01-12 15:00:00', 'UTC'),
    ]);

    $this->actingAs($user)
        ->withSession(['locale' => $locale])
        ->get(route('account'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('nextAppointment', fn (AssertableInertia $prop): AssertableInertia => $prop
                ->where('monthShort', $monthShort)
                ->etc()));
})->with([
    'english' => ['en', 'Jan'],
    'spanish' => ['es', 'ene.'],
    'french' => ['fr', 'janv.'],
]);
