<?php

declare(strict_types=1);

use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Enums\UserHistoryEvent;
use App\Models\User;
use App\Models\UserHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

use function Pest\Laravel\post;

/**
 * @param  array<string, mixed>  $attributes
 */
function historyDriver(array $attributes = []): User
{
    return User::query()->create([
        'name' => 'John Driver',
        'email' => 'john@example.com',
        'password' => 'secret-password',
        ...$attributes,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function profilePayload(array $overrides = []): array
{
    return [
        'name' => 'John Driver',
        'email' => 'john@example.com',
        'cellphone' => '',
        ...$overrides,
    ];
}

/*
 * The profile page and the profile-update endpoint sit behind password
 * confirmation. These tests are about what the profile does, not about the
 * gate, so they satisfy it up front. PasswordConfirmationTest covers the gate.
 */
beforeEach(function (): void {
    confirmPassword();
});

it('records a profile update with the before and after values', function (): void {
    Notification::fake();

    $user = historyDriver();

    $this->actingAs($user)
        ->put('/user/profile-information', profilePayload([
            'name' => 'Johnny Driver',
            'email' => 'johnny@example.com',
        ]))
        ->assertSessionHasNoErrors();

    $history = UserHistory::query()->where('user_id', $user->id)->sole();

    expect($history->event)->toBe(UserHistoryEvent::ProfileUpdated)
        ->and($history->changes)->toBe([
            'name' => ['from' => 'John Driver', 'to' => 'Johnny Driver'],
            'email' => ['from' => 'john@example.com', 'to' => 'johnny@example.com'],
        ]);
});

it('records that the license number changed without storing it', function (): void {
    $user = historyDriver();

    $this->actingAs($user)
        ->put('/user/profile-information', profilePayload([
            'drivers_license_number' => 'D1234567',
            'drivers_license_state' => 'Arizona',
            'drivers_license_country' => 'US',
            'drivers_license_expiration_date' => '2030-05-01',
        ]))
        ->assertSessionHasNoErrors();

    $history = UserHistory::query()->where('user_id', $user->id)->sole();

    expect($history->changes['drivers_license_number'])->toBe(['changed' => true])
        ->and($history->changes['drivers_license_state'])->toBe(['from' => null, 'to' => 'Arizona'])
        ->and($history->changes['drivers_license_country'])->toBe(['from' => null, 'to' => 'US'])
        ->and($history->changes['drivers_license_expiration_date'])->toBe(['from' => null, 'to' => '2030-05-01']);

    // The trail has no Encrypted cast of its own, so the raw column must never
    // have seen the plaintext in the first place.
    $raw = DB::table('user_history')->where('id', $history->id)->value('changes');

    expect($raw)->not->toContain('D1234567');
});

it('records nothing when the submitted profile matches what is already saved', function (): void {
    $user = historyDriver(['cellphone' => '+12015550123']);

    $this->actingAs($user)
        ->put('/user/profile-information', profilePayload(['cellphone' => '(201) 555-0123']))
        ->assertSessionHasNoErrors();

    expect(UserHistory::query()->where('user_id', $user->id)->count())->toBe(0);
});

it('records a password update', function (): void {
    $user = historyDriver();

    $this->actingAs($user)
        ->put('/user/password', [
            'current_password' => 'secret-password',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
        ->assertSessionHasNoErrors();

    $history = UserHistory::query()->where('user_id', $user->id)->sole();

    expect($history->event)->toBe(UserHistoryEvent::PasswordUpdated)
        ->and($history->changes)->toBeNull();
});

it('leaves the profile untouched when the history write fails', function (): void {
    $user = historyDriver();

    UserHistory::creating(function (): void {
        throw new RuntimeException('history write failed');
    });

    expect(function () use ($user): void {
        resolve(UpdateUserProfileInformation::class)
            ->update($user, profilePayload(['name' => 'Renamed Driver']));
    })->toThrow(RuntimeException::class);

    expect(User::query()->findOrFail($user->id)->name)->toBe('John Driver')
        ->and(UserHistory::query()->count())->toBe(0);
});

it('leaves the password untouched when the history write fails', function (): void {
    $user = historyDriver();
    $original = $user->password;

    UserHistory::creating(function (): void {
        throw new RuntimeException('history write failed');
    });

    $this->actingAs($user);

    expect(function () use ($user): void {
        resolve(UpdateUserPassword::class)->update($user, [
            'current_password' => 'secret-password',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);
    })->toThrow(RuntimeException::class);

    expect(User::query()->findOrFail($user->id)->password)->toBe($original)
        ->and(UserHistory::query()->count())->toBe(0);
});

it('records a password reset completed from the emailed link', function (): void {
    $user = historyDriver();

    post('/reset-password', [
        'token' => Password::createToken($user),
        'email' => $user->email,
        'password' => 'brand-new-password-123',
        'password_confirmation' => 'brand-new-password-123',
    ])->assertSessionHasNoErrors();

    $history = UserHistory::query()->where('user_id', $user->id)->sole();

    expect($history->event)->toBe(UserHistoryEvent::PasswordReset)
        ->and($history->changes)->toBeNull();
});
