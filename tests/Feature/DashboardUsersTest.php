<?php

declare(strict_types=1);

use App\Dashboard\UserHistoryEntryResolver;
use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\UserHistoryEvent;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\User;
use App\Models\UserHistory;
use App\Queries\DashboardUsers;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role as SpatieRole;

/*
 * The user data behind the admin users table and detail page. Gates are pinned
 * in DashboardPagesTest.
 */

function usersAdmin(): User
{
    return User::factory()->withRole(Role::Admin)->create(['name' => 'Ada Admin']);
}

it('pages the users table newest first', function (): void {
    $this->freezeTime();
    $admin = usersAdmin();
    User::factory()
        ->count(DashboardUsers::PER_PAGE)
        ->sequence(fn ($sequence): array => ['created_at' => now()->subMinutes($sequence->index + 1)])
        ->create();

    $this->actingAs($admin)
        ->get(route('dashboard.users'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Dashboard/Users/Index')
            ->has('users.data', DashboardUsers::PER_PAGE)
            ->where('users.data.0.uuid', $admin->uuid)
            ->where('users.total', DashboardUsers::PER_PAGE + 1));
});

it('sends staff roles only, so a driver has none', function (): void {
    $driver = User::factory()->withRole(Role::Driver)->create();
    CheckIn::factory()->count(2)->create(['user_id' => $driver->id]);

    $this->actingAs(usersAdmin())
        ->get(route('dashboard.users', ['type' => 'driver']))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('users.data', 1)
            ->where('users.data.0.uuid', $driver->uuid)
            ->where('users.data.0.roles', [])
            ->where('users.data.0.checkInsCount', 2));
});

it('splits staff from drivers, counting a role-less account as a driver', function (): void {
    User::factory()->withRole(Role::Shipping)->create();
    User::factory()->withRole(Role::Driver)->create();
    $roleless = User::factory()->create();

    $this->actingAs(usersAdmin())
        ->get(route('dashboard.users', ['type' => 'driver']))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('users.data', 2)
            ->where('users.data.0.uuid', $roleless->uuid)
            ->where('typeCounts', ['all' => 4, 'staff' => 2, 'driver' => 2]));
});

it('filters the users table by role, driver included', function (): void {
    $shipping = User::factory()->withRole(Role::Shipping)->create();
    $driver = User::factory()->withRole(Role::Driver)->create();
    User::factory()->withRole(Role::Sales)->create();

    $this->actingAs(usersAdmin())
        ->get(route('dashboard.users', ['roles' => ['shipping', 'driver']]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('users.data', 2)
            ->where('users.data.0.uuid', $driver->uuid)
            ->where('users.data.1.uuid', $shipping->uuid)
            ->where('typeCounts', ['all' => 2, 'staff' => 1, 'driver' => 1]));
});

it('searches the users table by name, email and phone', function (string $term): void {
    $match = User::factory()->create([
        'name' => 'Rosa Quintero',
        'email' => 'rosa@example.test',
        'cellphone' => '+13055550142',
    ]);
    User::factory()->create(['name' => 'Someone Else', 'cellphone' => '+17865550000']);

    $this->actingAs(usersAdmin())
        ->get(route('dashboard.users', ['search' => $term]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('users.data', 1)
            ->where('users.data.0.uuid', $match->uuid));
})->with(['name' => 'quintero', 'email' => 'rosa@example', 'phone' => '(305) 555-0142']);

it('treats LIKE wildcards in the users search as literal characters', function (): void {
    $admin = usersAdmin();

    $this->actingAs($admin)
        ->get(route('dashboard.users', ['search' => '__']))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('users.data', 0));
});

it('rejects an unknown role filter', function (): void {
    $this->actingAs(usersAdmin())
        ->get(route('dashboard.users', ['roles' => ['owner']]))
        ->assertSessionHasErrors('roles.0');
});

it('reads last activity from the session store', function (): void {
    $user = User::factory()->create();
    $lastActivity = now()->subHour()->startOfSecond();
    DB::table('sessions')->insert([
        ['id' => 'older', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->subDay()->getTimestamp()],
        ['id' => 'newer', 'user_id' => $user->id, 'payload' => '', 'last_activity' => $lastActivity->getTimestamp()],
    ]);

    $this->actingAs(usersAdmin())
        ->get(route('dashboard.users.show', ['uuid' => $user->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('user.lastActiveAt', $lastActivity->toIso8601String()));
});

it('shows a location-scoped user with the locations they work at', function (): void {
    $dock = Location::factory()->create(['name' => 'Miami - Dock', 'abbreviation' => 'MIA']);
    $user = User::factory()->withRole(Role::Shipping)->worksAt($dock)->create();
    Location::factory()->create();

    $this->actingAs(usersAdmin())
        ->get(route('dashboard.users.show', ['uuid' => $user->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Dashboard/Users/Show')
            ->where('user.uuid', $user->uuid)
            ->missing('user.id')
            ->where('user.roles', ['shipping'])
            ->where('user.hasAllLocations', false)
            ->has('user.locations', 1)
            ->where('user.locations.0.id', $dock->id)
            ->where('user.locations.0.abbreviation', 'MIA')
            ->where('user.locations.0.city', $dock->address->city));
});

it('flags the all-locations roles', function (Role $role, bool $hasAllLocations): void {
    $user = User::factory()->withRole($role)->create();

    $this->actingAs(usersAdmin())
        ->get(route('dashboard.users.show', ['uuid' => $user->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('user.hasAllLocations', $hasAllLocations));
})->with([
    'admin' => [Role::Admin, true],
    'sales' => [Role::Sales, true],
    'shipping manager' => [Role::ShippingManager, false],
    'driver' => [Role::Driver, false],
]);

it('flags all locations from the live grants, not the default roles', function (): void {
    SpatieRole::findByName(Role::Sales->value)->revokePermissionTo(Permission::ViewAllLocations);
    $sales = User::factory()->withRole(Role::Sales)->create();

    $this->actingAs(usersAdmin())
        ->get(route('dashboard.users.show', ['uuid' => $sales->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('user.hasAllLocations', false));
});

it('returns not found for a user that does not exist', function (): void {
    $this->actingAs(usersAdmin())
        ->get(route('dashboard.users.show', ['uuid' => fake()->uuid()]))
        ->assertNotFound();
});

it('does not resolve a user by their numeric id', function (): void {
    $user = User::factory()->create();

    $this->actingAs(usersAdmin())
        ->get('/dashboard/users/'.$user->id)
        ->assertNotFound();
});

it('shares the signed-in user by uuid, never by id', function (): void {
    $admin = usersAdmin();

    $this->actingAs($admin)
        ->get(route('dashboard.users'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('auth.user.uuid', $admin->uuid)
            ->missing('auth.user.id'));
});

it('pages the user history newest first', function (): void {
    $this->freezeTime();
    $user = User::factory()->create();
    UserHistory::factory()
        ->count(UserHistoryEntryResolver::PER_PAGE + 1)
        ->sequence(fn ($sequence): array => ['created_at' => now()->subMinutes($sequence->index)])
        ->create(['user_id' => $user->id]);
    $oldest = UserHistory::query()->oldest()->first();
    UserHistory::factory()->create();

    $this->actingAs(usersAdmin())
        ->get(route('dashboard.users.show', ['uuid' => $user->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('history.data', UserHistoryEntryResolver::PER_PAGE)
            ->where('history.data.0.createdAt', now()->toIso8601String())
            ->where('history.data.0.description', 'Updated their profile')
            ->where('history.total', UserHistoryEntryResolver::PER_PAGE + 1));

    $this->actingAs(usersAdmin())
        ->get(route('dashboard.users.show', ['uuid' => $user->uuid, 'page' => 2]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('history.data', 1)
            ->where('history.data.0.id', $oldest->id));
});

it('links each record a history entry touched', function (): void {
    $location = Location::factory()->create(['name' => 'Miami - Dock']);
    $checkIn = CheckIn::factory()->create(['location_id' => $location->id]);
    $appointment = Appointment::factory()->create(['location_id' => $location->id]);
    $attached = User::factory()->create(['name' => 'Sam Shipping']);
    $user = User::factory()->withRole(Role::ShippingManager)->create();
    UserHistory::factory()->create([
        'user_id' => $user->id,
        'event' => UserHistoryEvent::CheckInAppointmentLinked,
        'changes' => ['check_in' => $checkIn->uuid, 'appointment' => $appointment->uuid],
        'created_at' => now()->subMinute(),
    ]);
    UserHistory::factory()->create([
        'user_id' => $user->id,
        'event' => UserHistoryEvent::LocationUserAttached,
        'changes' => ['location' => $location->uuid, 'user' => $attached->uuid],
    ]);

    $this->actingAs(usersAdmin())
        ->get(route('dashboard.users.show', ['uuid' => $user->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('history.data.0.records', [
                ['type' => 'location', 'uuid' => $location->uuid, 'label' => 'Miami - Dock', 'available' => true],
                ['type' => 'user', 'uuid' => $attached->uuid, 'label' => 'Sam Shipping', 'available' => true],
            ])
            ->where('history.data.1.records', [
                ['type' => 'checkIn', 'uuid' => $checkIn->uuid, 'label' => $checkIn->reference_number, 'available' => true],
                ['type' => 'appointment', 'uuid' => $appointment->uuid, 'label' => $appointment->reference_number, 'available' => true],
            ]));
});

it('names a deleted check-in from its snapshot without linking it', function (): void {
    $user = User::factory()->withRole(Role::Admin)->create();
    $uuid = fake()->uuid();
    UserHistory::factory()->create([
        'user_id' => $user->id,
        'event' => UserHistoryEvent::CheckInDeleted,
        'changes' => ['check_in' => $uuid, 'reference_number' => 'CI-0042', 'location' => 'Miami - Dock', 'appointment' => null, 'reason' => 'Duplicate'],
    ]);

    $this->actingAs(usersAdmin())
        ->get(route('dashboard.users.show', ['uuid' => $user->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('history.data.0.records', [
                ['type' => 'checkIn', 'uuid' => $uuid, 'label' => 'CI-0042', 'available' => false],
            ])
            ->where('history.data.0.details', 'Reason: Duplicate'));
});

it('names a soft-deleted location without linking it', function (): void {
    $location = Location::factory()->create(['name' => 'Miami - Dock']);
    $location->delete();

    $user = User::factory()->withRole(Role::Admin)->create();
    UserHistory::factory()->create([
        'user_id' => $user->id,
        'event' => UserHistoryEvent::LocationEdited,
        'changes' => ['location' => $location->uuid, 'name' => ['from' => 'Miami', 'to' => 'Miami - Dock']],
    ]);

    $this->actingAs(usersAdmin())
        ->get(route('dashboard.users.show', ['uuid' => $user->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('history.data.0.records.0.label', 'Miami - Dock')
            ->where('history.data.0.records.0.available', false)
            ->where('history.data.0.details', 'Changed Name'));
});

it('hides a record at a location the viewer does not see', function (): void {
    $dock = Location::factory()->create();
    $checkIn = CheckIn::factory()->create(['location_id' => Location::factory()->create()->id]);
    $viewer = User::factory()->withRole(Role::ShippingManager)->worksAt($dock)->create();
    $viewer->givePermissionTo(Permission::ViewUsers);

    $user = User::factory()->withRole(Role::Admin)->create();
    UserHistory::factory()->create([
        'user_id' => $user->id,
        'event' => UserHistoryEvent::CheckInNoteAdded,
        'changes' => ['check_in' => $checkIn->uuid],
    ]);

    $this->actingAs($viewer)
        ->get(route('dashboard.users.show', ['uuid' => $user->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('history.data.0.records', [
                ['type' => 'checkIn', 'uuid' => $checkIn->uuid, 'label' => 'Check-in', 'available' => false],
            ]));
});

it('describes what an action involved', function (UserHistoryEvent $event, array $changes, ?string $details): void {
    $user = User::factory()->create();
    UserHistory::factory()->create(['user_id' => $user->id, 'event' => $event, 'changes' => $changes]);

    $this->actingAs(usersAdmin())
        ->get(route('dashboard.users.show', ['uuid' => $user->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('history.data.0.details', $details));
})->with([
    'profile fields, redacted included' => [UserHistoryEvent::ProfileUpdated, ['name' => ['from' => 'A', 'to' => 'B'], 'drivers_license_number' => ['changed' => true]], 'Changed Name, License number'],
    'pdf recipient' => [UserHistoryEvent::CheckInPdfEmailed, ['check_in' => fake()->uuid(), 'recipient' => 'ops@example.com', 'status' => 'pending'], 'Sent to ops@example.com'],
    'closure date and reason' => [UserHistoryEvent::LocationClosureAdded, ['location' => fake()->uuid(), 'date' => '2026-12-25', 'reason' => 'Holiday', 'appliesTo' => ['checkin']], 'For Dec 25, 2026. Reason: Holiday'],
    'nothing to add' => [UserHistoryEvent::PasswordUpdated, [], null],
]);

it('rejects a history page that is not a positive number', function (): void {
    $user = User::factory()->create();

    $this->actingAs(usersAdmin())
        ->get(route('dashboard.users.show', ['uuid' => $user->uuid, 'page' => 0]))
        ->assertInvalid('page');
});
