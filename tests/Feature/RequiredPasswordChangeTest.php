<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Enums\UserHistoryEvent;
use App\Models\User;
use App\Models\UserHistory;
use Illuminate\Support\Facades\Hash;

/*
 * Holding a user whose account an admin created with a temporary password on
 * the change page until they choose their own.
 */

function temporaryPasswordUser(): User
{
    $user = User::factory()->withRole(Role::Shipping)->create(['password' => 'Temp-Pass-2026!']);
    $user->forceFill(['password_change_required_at' => now()])->save();

    return $user;
}

it('holds the user on the change page but lets them sign out', function (): void {
    $user = temporaryPasswordUser();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('password.change'));
    $this->actingAs($user)->get(route('account'))->assertRedirect(route('password.change'));

    $this->actingAs($user)->get(route('password.change'))
        ->assertInertia(fn ($page) => $page->component('Auth/ChoosePassword')->where('mode', 'required'));

    $this->actingAs($user)->post(route('logout'))->assertRedirect();
    $this->assertGuest();
});

it('refuses the temporary password itself', function (): void {
    $this->actingAs(temporaryPasswordUser())
        ->put(route('password.change.update'), ['password' => 'Temp-Pass-2026!', 'password_confirmation' => 'Temp-Pass-2026!'])
        ->assertSessionHasErrors(['password' => __('messages.choosePassword.required.samePassword')]);
});

it('releases the user once they choose their own password', function (): void {
    $user = temporaryPasswordUser();

    $this->actingAs($user)
        ->put(route('password.change.update'), ['password' => 'Own-Pass-2026!', 'password_confirmation' => 'Own-Pass-2026!'])
        ->assertRedirect(route('dashboard'));

    $user->refresh();

    expect($user->mustChangePassword())->toBeFalse()
        ->and(Hash::check('Own-Pass-2026!', $user->password))->toBeTrue()
        ->and(UserHistory::query()->where('user_id', $user->id)->pluck('event')->all())->toBe([UserHistoryEvent::PasswordUpdated]);

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

it('sends a user with nothing to change home', function (): void {
    $this->actingAs(User::factory()->withRole(Role::Shipping)->create())
        ->get(route('password.change'))
        ->assertRedirect(route('dashboard'));
});
