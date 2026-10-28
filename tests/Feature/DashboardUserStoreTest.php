<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\RecordHistoryEvent;
use App\Enums\Role;
use App\Enums\UserHistoryEvent;
use App\Models\Location;
use App\Models\User;
use App\Models\UserHistory;
use App\Notifications\UserInvitation;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role as SpatieRole;

/*
 * Creating staff accounts from the add user dialog on the users page.
 */

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function newUserPayload(array $overrides = []): array
{
    return [
        'name' => 'Rosa Diaz',
        'email' => 'rosa@example.com',
        'cellphone' => '',
        'role' => Role::Sales->value,
        'locations' => [],
        'access' => 'invite',
        'password' => null,
        ...$overrides,
    ];
}

it('creates a location-scoped user, attaches their locations and emails an invite', function (): void {
    Notification::fake();
    $admin = User::factory()->withRole(Role::Admin)->create();
    $location = Location::factory()->create();

    $this->actingAs($admin)
        ->from(route('dashboard.users'))
        ->post(route('dashboard.users.store'), newUserPayload([
            'email' => ' Rosa@Example.com ',
            'cellphone' => '(555) 123-4567',
            'role' => Role::Shipping->value,
            'locations' => [$location->uuid],
        ]))
        ->assertRedirect(route('dashboard.users'))
        ->assertInertiaFlash('createdUser.name', 'Rosa Diaz')
        ->assertInertiaFlash('createdUser.access', 'invite');

    $user = User::query()->where('email', 'rosa@example.com')->sole();

    expect($user->hasRole(Role::Shipping))->toBeTrue()
        ->and($user->cellphone)->toBe('+15551234567')
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->mustChangePassword())->toBeFalse()
        ->and($user->locations()->pluck('locations.id')->all())->toBe([$location->id])
        ->and($location->history()->pluck('event')->all())->toBe([RecordHistoryEvent::LocationUserAttached])
        ->and(UserHistory::query()->where('user_id', $admin->id)->where('event', UserHistoryEvent::UserCreated)->sole()->changes)
        ->toBe(['user' => $user->uuid, 'role' => 'shipping', 'access' => 'invite']);

    Notification::assertSentTo($user, UserInvitation::class, function (UserInvitation $notification) use ($user, $admin): bool {
        $mail = $notification->toMail($user);

        return $mail->viewData['inviter'] === $admin->name
            && $mail->viewData['expiresInDays'] === 7
            && str_starts_with($mail->viewData['url'], route('invitation.show', ['token' => $notification->token]));
    });
});

it('gives a temporary password the user must change, and sends no invite', function (): void {
    Notification::fake();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.users.store'), newUserPayload([
            'access' => 'temporary-password',
            'password' => 'Temp-Pass-2026!',
        ]))
        ->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'rosa@example.com')->sole();

    expect(Hash::check('Temp-Pass-2026!', $user->password))->toBeTrue()
        ->and($user->mustChangePassword())->toBeTrue();

    Notification::assertNothingSent();
});

it('attaches no locations to a role that sees every location', function (): void {
    Notification::fake();
    $location = Location::factory()->create();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.users.store'), newUserPayload([
            'role' => Role::Sales->value,
            'locations' => [$location->uuid],
        ]))
        ->assertSessionHasNoErrors();

    expect(User::query()->where('email', 'rosa@example.com')->sole()->locations()->count())->toBe(0);
});

it('requires a location for a role that sees only its own, read from the live grants', function (): void {
    SpatieRole::findByName(Role::Sales->value)->revokePermissionTo(Permission::ViewAllLocations->value);

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.users.store'), newUserPayload(['role' => Role::Sales->value]))
        ->assertSessionHasErrors(['locations' => 'Pick at least one location, or this user will see nothing.']);

    expect(User::query()->where('email', 'rosa@example.com')->exists())->toBeFalse();
});

it('rejects a location that does not exist', function (): void {
    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.users.store'), newUserPayload([
            'role' => Role::Shipping->value,
            'locations' => [fake()->uuid()],
        ]))
        ->assertSessionHasErrors(['locations' => 'One of these locations is no longer available.']);
});

it('only creates staff, never drivers', function (): void {
    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.users.store'), newUserPayload(['role' => Role::Driver->value]))
        ->assertSessionHasErrors('role');
});

it('refuses an email that already has an account', function (): void {
    User::factory()->create(['email' => 'rosa@example.com']);

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.users.store'), newUserPayload(['email' => 'ROSA@example.com']))
        ->assertSessionHasErrors(['email' => 'Someone already has an account with this email.']);
});

it('requires the temporary password when that is the chosen method', function (): void {
    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.users.store'), newUserPayload(['access' => 'temporary-password']))
        ->assertSessionHasErrors(['password' => 'Set a temporary password, or send an invite instead.']);
});

it('refuses staff without create users', function (): void {
    $this->actingAs(User::factory()->withRole(Role::ShippingManager)->create())
        ->post(route('dashboard.users.store'), newUserPayload())
        ->assertForbidden();

    expect(User::query()->where('email', 'rosa@example.com')->exists())->toBeFalse();
});

it('loads the dialog options only when asked, marking which roles see every location', function (): void {
    $admin = User::factory()->withRole(Role::Admin)->create();
    $location = Location::factory()->create(['name' => 'Alpha Dock']);

    $this->actingAs($admin)
        ->get(route('dashboard.users'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->missing('newUserRoles')
            ->reloadOnly(['newUserRoles', 'newUserLocations'], fn (AssertableInertia $reload): AssertableInertia => $reload
                ->where('newUserRoles', [
                    ['value' => 'admin', 'seesAllLocations' => true],
                    ['value' => 'shipping-manager', 'seesAllLocations' => false],
                    ['value' => 'shipping', 'seesAllLocations' => false],
                    ['value' => 'sales', 'seesAllLocations' => true],
                ])
                ->where('newUserLocations', [
                    ['uuid' => $location->uuid, 'name' => 'Alpha Dock', 'city' => $location->address->city],
                ])));
});

it("names the role and sign-in method on the admin's activity trail", function (): void {
    Notification::fake();
    $admin = User::factory()->withRole(Role::Admin)->create();

    $this->actingAs($admin)->post(route('dashboard.users.store'), newUserPayload(['role' => Role::Sales->value]));

    $this->actingAs($admin)
        ->get(route('dashboard.users.show', ['uuid' => $admin->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('history.data.0.description', 'Added a user')
            ->where('history.data.0.records.0.label', 'Rosa Diaz')
            ->where('history.data.0.details', 'Role: Sales. Invited by email'));
});
