<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Enums\UserHistoryEvent;
use App\Models\User;
use App\Models\UserHistory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

/*
 * Redeeming the invitation an admin sends a new staff member.
 */

it('sets the first password, signs the user in and works only once', function (): void {
    $user = User::factory()->withRole(Role::Shipping)->create(['email' => 'rosa@example.com']);
    $token = Password::broker('invites')->createToken($user);
    $payload = ['token' => $token, 'email' => 'rosa@example.com', 'password' => 'New-Pass-2026!', 'password_confirmation' => 'New-Pass-2026!'];

    $this->get(route('invitation.show', ['token' => $token, 'email' => 'rosa@example.com']))
        ->assertInertia(fn ($page) => $page
            ->component('Auth/ChoosePassword')
            ->where('mode', 'invitation')
            ->where('email', 'rosa@example.com')
            ->has('translations.choosePassword'));

    $this->post(route('invitation.accept'), $payload)->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect(Hash::check('New-Pass-2026!', $user->refresh()->password))->toBeTrue()
        ->and(UserHistory::query()->where('user_id', $user->id)->pluck('event')->all())->toBe([UserHistoryEvent::InvitationAccepted]);

    auth()->logout();

    $this->post(route('invitation.accept'), $payload)
        ->assertSessionHasErrors(['email' => __('messages.choosePassword.invitation.invalid')]);
});

it('refuses an invitation older than a week', function (): void {
    $user = User::factory()->withRole(Role::Shipping)->create();
    $token = Password::broker('invites')->createToken($user);

    $this->travel(8)->days();

    $this->post(route('invitation.accept'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'New-Pass-2026!',
        'password_confirmation' => 'New-Pass-2026!',
    ])->assertSessionHasErrors(['email' => __('messages.choosePassword.invitation.invalid')]);

    $this->assertGuest();
});

it('never redeems a password reset token as an invitation', function (): void {
    $user = User::factory()->create();
    $token = Password::broker('users')->createToken($user);

    $this->post(route('invitation.accept'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'New-Pass-2026!',
        'password_confirmation' => 'New-Pass-2026!',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});
